<?php

/**
 * HTTP traffic fuzzer for the thread fragment cache, against a running scratch install.
 *
 * Phase A is reads only: index pages, thread pages, last-replies views and overboards under
 * different blacklist cookies, plus hostile parameters, all at once and starting from an empty
 * cache so misses race each other. Nothing changes underneath, so every response for a URL must
 * be the same, whether it was drawn, read back, or half of each.
 *
 * Phase B adds writers to the same traffic: votes (which leave the stamp alone), replies, new
 * threads and deletions (which move it), on several boards at once.
 *
 * Phase C aims at the one window random traffic rarely hits: a board's fragments are dropped, a
 * reader starts drawing the page, and a vote lands a few milliseconds into the draw.
 *
 * After each phase the traffic stops and every URL is fetched as the cache has it, then again
 * with the cache emptied. The second is a fresh render of the same rows, so any difference is a
 * fragment that outlived the change it should have been dropped for.
 *
 * Usage (the install under test must point at a koko_test* / koko_bench* database):
 *   KOKO_TEST_DSN=... KOKO_TEST_USER=... KOKO_TEST_PASS=... \
 *   php tests/stress/fragmentTraffic.php --base=http://127.0.0.1:8097 --storages=/path/to/global/board-storages
 *     --reads=2000        Phase A requests
 *     --seconds=20        Phase B duration
 *     --concurrency=24    Requests in flight
 *     --write-share=20    Percent of phase B requests that write
 *     --hot=6             Threads per board that take most of the traffic
 *     --races=30          Phase C trials
 *     --seed=12345
 *
 * Exit code is 0 when every invariant held, 1 otherwise.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$opt = ['base' => '', 'storages' => '', 'reads' => 2000, 'seconds' => 20, 'concurrency' => 24, 'write-share' => 20, 'hot' => 6, 'races' => 30, 'seed' => random_int(1, PHP_INT_MAX >> 8)];
foreach (array_slice($argv, 1) as $arg) {
	if (!preg_match('/^--([a-z-]+)=(.+)$/', $arg, $m) || !isset($opt[$m[1]])) {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(2);
	}
	$opt[$m[1]] = is_int($opt[$m[1]]) ? (int)$m[2] : $m[2];
}
$base = rtrim($opt['base'], '/');
$storages = rtrim($opt['storages'], '/');
if ($base === '' || !is_dir($storages)) {
	exit("Needs --base=URL and --storages=DIR (the install's global/board-storages).\n");
}

$dsn = getenv('KOKO_TEST_DSN') ?: '';
if (!preg_match('/dbname=(koko_(?:test|bench)\w*)/', $dsn)) {
	exit("KOKO_TEST_DSN must name a koko_test* or koko_bench* database: this script posts and deletes.\n");
}
$pdo = new PDO($dsn, getenv('KOKO_TEST_USER') ?: '', getenv('KOKO_TEST_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$tables = require dirname(__DIR__, 2) . '/tables.php';
[$postTable, $threadTable, $boardTable] = [$tables['POST_TABLE'], $tables['THREAD_TABLE'], $tables['BOARD_TABLE']];

mt_srand($opt['seed']);
$runId = 'fuzz' . dechex($opt['seed'] & 0xffffff);
const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0';
const NAV_SENTINEL = '<!--koko:threadnav-->';
const BLANKET_ERROR = 'There has been an error.';

// ---- What there is to read ---------------------------------------------------

$boards = [];
foreach ($pdo->query("SELECT board_uid, board_identifier, storage_directory_name FROM {$boardTable} WHERE board_uid > 0 AND listed = 1") as $row) {
	$threads = $pdo->prepare("SELECT t.thread_uid, t.post_op_number, (SELECT COUNT(*) FROM {$postTable} p WHERE p.thread_uid = t.thread_uid) AS posts
		FROM {$threadTable} t WHERE t.boardUID = ? ORDER BY t.last_bump_time DESC LIMIT 40");
	$threads->execute([$row['board_uid']]);
	$threads = $threads->fetchAll();
	if ($threads === []) {
		continue;
	}
	foreach (array_slice($threads, 0, $opt['hot'], true) as $i => $thread) {
		// the OP and the last few replies are what an index preview shows
		$votable = $pdo->prepare("(SELECT post_uid FROM {$postTable} WHERE thread_uid = ? AND host <> '127.0.0.1' AND is_op = 1)
			UNION (SELECT post_uid FROM {$postTable} WHERE thread_uid = ? AND host <> '127.0.0.1' ORDER BY post_uid DESC LIMIT 3)");
		$votable->execute([$thread['thread_uid'], $thread['thread_uid']]);
		$threads[$i]['votable'] = $votable->fetchAll(PDO::FETCH_COLUMN);
	}
	$boards[(int)$row['board_uid']] = ['uid' => (int)$row['board_uid'], 'id' => $row['board_identifier'], 'storage' => $row['storage_directory_name'], 'threads' => $threads];
}
if ($boards === []) {
	exit("No listed board with threads in this database.\n");
}
$boardUids = array_keys($boards);
// a few boards take most of the traffic, so requests land on each other
$hotBoards = array_slice($boardUids, 0, 3);

$pick = static fn(array $from) => $from[mt_rand(0, count($from) - 1)];
$pickBoard = static fn() => $boards[mt_rand(0, 9) < 7 ? $pick($hotBoards) : $pick($boardUids)];
$pickThread = static function (array $board) use ($opt): array {
	$hot = array_slice($board['threads'], 0, $opt['hot']);
	return mt_rand(0, 9) < 8 ? $hot[mt_rand(0, count($hot) - 1)] : $board['threads'][mt_rand(0, count($board['threads']) - 1)];
};

$blacklists = [null, null, '[]', json_encode([$boardUids[0]]), json_encode(array_slice($boardUids, 1, 3)), json_encode(array_slice($boardUids, 0, -1)),
	json_encode($boardUids), '{not json', '[[1],"x",-5,99999]', str_repeat('9', 400)];

/** One read, as [kind, url, cookie]. */
$nextRead = static function () use ($base, $boards, $pick, $pickBoard, $pickThread, $blacklists): array {
	$board = $pickBoard();
	$url = $base . '/' . $board['id'] . '/koko.php';
	$roll = mt_rand(1, 100);
	if ($roll <= 30) {
		return ['index', $url . '?page=' . (mt_rand(0, 9) < 6 ? mt_rand(1, 2) : mt_rand(3, 9)), null];
	}
	if ($roll <= 60) {
		$thread = $pickThread($board);
		$pages = (int)ceil($thread['posts'] / 100) + 1;
		return ['thread', $url . '?res=' . $thread['post_op_number'] . (mt_rand(0, 2) ? '&page=' . mt_rand(1, $pages) : ''), null];
	}
	if ($roll <= 66) {
		return ['recent', $url . '?res=' . $pickThread($board)['post_op_number'] . '&recentReplies=' . $pick([1, 5, 50, 9999]), null];
	}
	if ($roll <= 93) {
		$cookie = $pick($blacklists);
		return ['overboard', $url . '?mode=overboard&page=' . (mt_rand(0, 9) < 7 ? mt_rand(1, 2) : mt_rand(3, 12)), $cookie === null ? null : 'overboard_black_list=' . rawurlencode($cookie)];
	}
	return ['hostile', $url . $pick(['?page=0', '?page=-1', '?page=99999999', '?page=abc', '?page[]=1', '?res=999999999', '?res=-4', '?res=1e9', '?res=' . $pickThread($board)['post_op_number'] . '&page=-7',
		'?res=' . $pickThread($board)['post_op_number'] . '&page=9999', '?mode=overboard&page=-1', '?mode=overboard&page=99999', '?mode=overboard&page=%00', '?res=%27%22%3E']), null];
};

// ---- Plumbing ----------------------------------------------------------------

/** Drop what legitimately differs between two draws of the same page. */
function normalizeBody(string $body): string {
	$body = preg_replace('/(name="csrf_token" value=")[0-9a-f]+/', '$1X', $body);
	$body = preg_replace('/(data-csrf(?:-token)?=")[0-9a-f]+/', '$1X', $body);
	$body = preg_replace('/((?:index|\d+)\.html\?)\d+/', '$1T', $body);
	// the word filter colours its replacements at random on every draw
	return preg_replace('/rgb\(\d+, \d+, \d+\)/', 'rgb(X)', $body);
}

/** What is wrong with a response on its face, whatever it was meant to hold. */
function inspectResponse(array $request, int $status, string $body, array $boards): array {
	$problems = [];
	[$kind, $url, $cookie] = [$request['kind'], $request['url'], $request['cookie']];
	// a refusal is a page of its own, often under HTTP 500; what nothing caught gets the blanket message
	if ($status === 0 || str_contains($body, BLANKET_ERROR)) {
		$problems[] = "unhandled error, HTTP $status";
	} elseif (in_array($kind, ['index', 'thread', 'recent', 'overboard'], true) && !in_array($status, [200, 302, 404], true)) {
		$problems[] = "HTTP $status";
	}
	if (str_contains($body, NAV_SENTINEL)) {
		$problems[] = 'navigation sentinel left in the page';
	}
	if (preg_match('~<b>(Warning|Notice|Deprecated|Fatal error|Parse error)</b>|Stack trace:|Uncaught ~', $body, $m)) {
		$problems[] = 'PHP error in the page: ' . $m[0];
	}
	if ($status === 200 && in_array($kind, ['index', 'thread', 'recent', 'overboard'], true)) {
		if (!str_contains(substr($body, -200), '</html>')) {
			$problems[] = 'page is cut short';
		}
		preg_match_all('/id="p(\d+)_\d+"/', $body, $m);
		$seen = array_map('intval', array_unique($m[1]));
		if ($kind !== 'overboard') {
			preg_match('~/([a-z0-9]+)/koko\.php~', $url, $b);
			$own = array_values(array_filter($boards, static fn(array $board): bool => $board['id'] === $b[1]))[0]['uid'] ?? 0;
			if (array_diff($seen, [$own]) !== []) {
				$problems[] = "posts of another board on board $own's page: " . implode(',', array_diff($seen, [$own]));
			}
		} elseif ($cookie !== null) {
			$blacklist = json_decode(rawurldecode(substr($cookie, strlen('overboard_black_list='))), true);
			$blocked = is_array($blacklist) ? array_filter($blacklist, 'is_int') : [];
			if (is_array($blacklist) && $blocked === $blacklist && array_intersect($seen, $blocked) !== []) {
				$problems[] = 'blacklisted board on the overboard: ' . implode(',', array_intersect($seen, $blocked));
			}
		}
	}
	return $problems;
}

/**
 * Keep $concurrency requests in flight until $next returns null.
 *
 * @param callable():?array $next A request: kind, url, cookie, and optionally post, jar, ajax, referer.
 * @param callable(array,int,string,float):void $done
 */
function runTraffic(int $concurrency, callable $next, callable $done): void {
	$multi = curl_multi_init();
	$inFlight = [];
	$launch = static function () use ($multi, &$inFlight, $next): bool {
		$request = $next();
		if ($request === null) {
			return false;
		}
		$ch = curl_init($request['url']);
		$headers = ['Accept: text/html'];
		if (!empty($request['ajax'])) {
			$headers[] = 'X-Requested-With: XMLHttpRequest';
		}
		curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => USER_AGENT,
			CURLOPT_HTTPHEADER => $headers, CURLOPT_REFERER => $request['referer'] ?? $request['url']]);
		if (($request['cookie'] ?? null) !== null) {
			curl_setopt($ch, CURLOPT_COOKIE, $request['cookie']);
		}
		if (isset($request['jar'])) {
			curl_setopt($ch, CURLOPT_COOKIEFILE, $request['jar']);
		}
		if (isset($request['post'])) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request['post']);
		}
		curl_multi_add_handle($multi, $ch);
		$inFlight[(int)$ch] = [$ch, $request, microtime(true)];
		return true;
	};

	$more = true;
	do {
		while ($more && count($inFlight) < $concurrency) {
			$more = $launch();
		}
		curl_multi_exec($multi, $running);
		curl_multi_select($multi, 0.05);
		while ($info = curl_multi_info_read($multi)) {
			[$ch, $request, $started] = $inFlight[(int)$info['handle']];
			unset($inFlight[(int)$info['handle']]);
			$done($request, (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string)curl_multi_getcontent($ch), microtime(true) - $started);
			curl_multi_remove_handle($multi, $ch);
			curl_close($ch);
		}
	} while ($more || $inFlight !== []);
	curl_multi_close($multi);
}

$clearFragments = static function () use ($boards, $storages): int {
	$dropped = 0;
	foreach ($boards as $board) {
		foreach (glob($storages . '/' . $board['storage'] . '/cache/threads/*.html') ?: [] as $file) {
			$dropped += (int)@unlink($file);
		}
	}
	return $dropped;
};

/** Wait for the rebuilds that replies and deletions handed to the background. */
$waitForBackground = static function (): void {
	$deadline = microtime(true) + 60;
	do {
		usleep(200000);
		$busy = trim((string)shell_exec("pgrep -fc 'background-runner\\.php' 2>/dev/null"));
	} while ((int)$busy > 0 && microtime(true) < $deadline);
	usleep(200000);
};

$failures = [];
$fail = static function (string $what) use (&$failures): void {
	$failures[$what] = ($failures[$what] ?? 0) + 1;
};
$latency = [];
$fetchAll = static function (array $requests, int $concurrency) use ($boards, $fail): array {
	$results = [];
	$queue = array_values($requests);
	runTraffic($concurrency, static function () use (&$queue): ?array {
		return array_shift($queue);
	}, static function (array $request, int $status, string $body) use (&$results, $boards, $fail): void {
		foreach (inspectResponse($request, $status, $body, $boards) as $problem) {
			$fail("{$request['kind']}: $problem ({$request['url']})");
		}
		$results[$request['key']] = [$status, normalizeBody($body)];
	});
	return $results;
};

/** Where two pages part ways, for the report. */
function firstDifference(string $a, string $b): string {
	$at = strspn($a ^ $b, "\0");
	$clean = static fn(string $s): string => preg_replace('/\s+/', ' ', substr($s, max(0, $at - 60), 160));
	return "at byte $at\n              cache: …" . $clean($a) . "\n              fresh: …" . $clean($b);
}

/**
 * With nothing changing: each page as the cache serves it, then as a fresh render draws it.
 *
 * @return string[] The pages the cache got wrong.
 */
$verifyAtRest = static function (array $requests) use ($fetchAll, $clearFragments, $waitForBackground): array {
	$waitForBackground();
	$cached = $fetchAll($requests, 8);
	$clearFragments();
	$fresh = $fetchAll($requests, 8);

	$suspects = array_filter($requests, static fn(array $r): bool => $cached[$r['key']] !== $fresh[$r['key']]);
	if ($suspects === []) {
		return [];
	}
	// a page that differs between two fresh renders differs for a reason of its own
	$clearFragments();
	$again = $fetchAll($suspects, 8);
	$wrong = [];
	foreach ($suspects as $request) {
		$key = $request['key'];
		if ($again[$key] !== $fresh[$key]) {
			echo "  note  not deterministic, skipped: {$key}\n";
			continue;
		}
		$wrong[] = $key . ' (HTTP ' . $cached[$key][0] . ' vs ' . $fresh[$key][0] . ') ' . firstDifference($cached[$key][1], $fresh[$key][1]);
	}
	return $wrong;
};

$asRequest = static fn(array $read): array => ['kind' => $read[0], 'url' => $read[1], 'cookie' => $read[2], 'key' => $read[1] . ($read[2] === null ? '' : ' [' . rawurldecode($read[2]) . ']')];

$report = static function (string $title, array $counts, array $latency): void {
	echo "\n$title\n";
	foreach ($counts as $kind => $n) {
		$times = $latency[$kind] ?? [0];
		sort($times);
		printf("  %-10s %6d   median %4d ms   p95 %4d ms   max %5d ms\n", $kind, $n, $times[(int)(count($times) * 0.5)] * 1000, $times[(int)(count($times) * 0.95)] * 1000, end($times) * 1000);
	}
};

printf("seed %d, %d boards, %d in flight, against %s\n", $opt['seed'], count($boards), $opt['concurrency'], $base);

// ---- Phase A: reads over a cold cache ----------------------------------------

$clearFragments();
$seenBodies = [];
$requestsByKey = [];
$counts = [];
$latency = [];
$left = $opt['reads'];
$started = microtime(true);
runTraffic($opt['concurrency'], static function () use (&$left, $nextRead, $asRequest): ?array {
	return $left-- > 0 ? $asRequest($nextRead()) : null;
}, static function (array $request, int $status, string $body, float $took) use (&$seenBodies, &$requestsByKey, &$counts, &$latency, $boards, $fail): void {
	$counts[$request['kind']] = ($counts[$request['kind']] ?? 0) + 1;
	$latency[$request['kind']][] = $took;
	foreach (inspectResponse($request, $status, $body, $boards) as $problem) {
		$fail("{$request['kind']}: $problem ({$request['url']})");
	}
	$requestsByKey[$request['key']] = $request;
	$seenBodies[$request['key']][$status . ':' . sha1(normalizeBody($body))] = true;
});
$report(sprintf('Phase A: %d reads of %d different pages in %.1fs (%.0f/s), cache cold at the start', $opt['reads'], count($requestsByKey), microtime(true) - $started, $opt['reads'] / (microtime(true) - $started)), $counts, $latency);

$unstable = array_keys(array_filter($seenBodies, static fn(array $bodies): bool => count($bodies) > 1));
$wrongA = $verifyAtRest($requestsByKey);
// a page that a fresh render cannot reproduce either is not the cache's doing
$unstable = array_values(array_filter($unstable, static fn(string $key): bool => (bool)array_filter($wrongA, static fn(string $w): bool => str_starts_with($w, $key . ' '))));

// ---- Phase B: the same reads, with writers ------------------------------------

$sessions = [];
for ($i = 0; $i < 4; $i++) {
	$board = $boards[$hotBoards[$i % count($hotBoards)]];
	$jar = sys_get_temp_dir() . '/koko-traffic-' . getmypid() . '-' . $i . '.jar';
	$ch = curl_init($base . '/' . $board['id'] . '/koko.php?res=' . $board['threads'][0]['post_op_number']);
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => USER_AGENT, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => '']);
	$page = (string)curl_exec($ch);
	curl_close($ch);
	if (preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $page, $m)) {
		$sessions[] = ['jar' => $jar, 'token' => $m[1]];
	}
}
if ($sessions === []) {
	$fail('no session could be opened for posting');
}

$ownPosts = $pdo->prepare("SELECT p.post_uid, b.board_identifier FROM {$postTable} p JOIN {$boardTable} b ON b.board_uid = p.boardUID WHERE p.com LIKE ? ORDER BY RAND() LIMIT 1");
$nextWrite = static function () use ($base, $boards, $hotBoards, $pick, $pickThread, $sessions, $runId, $ownPosts): ?array {
	$board = $boards[$pick($hotBoards)];
	$url = $base . '/' . $board['id'] . '/koko.php';
	$thread = $pickThread($board);
	$session = $sessions === [] ? null : $pick($sessions);
	$roll = mt_rand(1, 100);

	if ($roll <= 70 || $session === null) {
		// a vote leaves the post count and the reply time as they were
		if (empty($thread['votable'])) {
			return null;
		}
		return ['kind' => 'vote', 'url' => $url . '?mode=module&load=soudane&postUid=' . $pick($thread['votable']) . '&type=' . $pick(['yeah', 'yeah', 'nope']), 'cookie' => null, 'ajax' => true, 'key' => 'vote'];
	}
	if ($roll <= 93 && $roll > 88) {
		// a new thread: its row goes in before its OP does, and is pointed at the OP afterwards
		return ['kind' => 'thread+', 'url' => $url, 'cookie' => null, 'jar' => $session['jar'], 'referer' => $url, 'key' => 'thread+',
			'post' => ['mode' => 'regist', 'sub' => $runId, 'com' => $runId . ' thread ' . bin2hex(random_bytes(6)), 'pwd' => 'fuzzpw', 'csrf_token' => $session['token']]];
	}
	if ($roll <= 88) {
		return ['kind' => 'reply', 'url' => $url, 'cookie' => null, 'jar' => $session['jar'], 'referer' => $url . '?res=' . $thread['post_op_number'], 'key' => 'reply',
			'post' => ['mode' => 'regist', 'resto' => $thread['post_op_number'], 'com' => $runId . ' ' . bin2hex(random_bytes(6)) . "\n>>" . $thread['post_op_number'], 'pwd' => 'fuzzpw',
				'email' => mt_rand(0, 1) ? 'sage' : '', 'csrf_token' => $session['token']]];
	}
	$ownPosts->execute([$runId . '%']);
	$own = $ownPosts->fetch();
	if ($own === false) {
		return null;
	}
	return ['kind' => 'delete', 'url' => $base . '/' . $own['board_identifier'] . '/koko.php', 'cookie' => null, 'jar' => $session['jar'], 'key' => 'delete',
		'post' => ['mode' => 'usrdel', 'func' => 'delete', (string)$own['post_uid'] => 'delete', 'pwd' => 'fuzzpw', 'csrf_token' => $session['token']]];
};

$counts = [];
$latency = [];
$statuses = [];
$requestsB = [];
$deadline = microtime(true) + $opt['seconds'];
$started = microtime(true);
runTraffic($opt['concurrency'], static function () use ($deadline, $opt, $nextRead, $nextWrite, $asRequest): ?array {
	if (microtime(true) >= $deadline) {
		return null;
	}
	return (mt_rand(1, 100) <= $opt['write-share'] ? $nextWrite() : null) ?? $asRequest($nextRead());
}, static function (array $request, int $status, string $body, float $took) use (&$counts, &$latency, &$statuses, &$requestsB, $boards, $fail): void {
	$counts[$request['kind']] = ($counts[$request['kind']] ?? 0) + 1;
	$latency[$request['kind']][] = $took;
	foreach (inspectResponse($request, $status, $body, $boards) as $problem) {
		$fail("{$request['kind']}: $problem ({$request['url']})");
	}
	if (in_array($request['kind'], ['vote', 'reply', 'thread+', 'delete'], true)) {
		$statuses[$request['kind']][$status] = ($statuses[$request['kind']][$status] ?? 0) + 1;
	} else {
		$requestsB[$request['key']] = $request;
	}
});
$total = array_sum($counts);
$report(sprintf('Phase B: %d requests in %.1fs (%.0f/s), %d%% of them writes', $total, microtime(true) - $started, $total / (microtime(true) - $started), $opt['write-share']), $counts, $latency);
foreach ($statuses as $kind => $byStatus) {
	ksort($byStatus);
	echo "  $kind responses: " . implode(', ', array_map(static fn(int $s, int $n): string => "HTTP $s x$n", array_keys($byStatus), $byStatus)) . "\n";
}

// the hot pages are the ones the writers touched, whether or not a reader happened to ask for them
foreach ($hotBoards as $uid) {
	foreach ([1, 2] as $page) {
		$read = $asRequest(['index', $base . '/' . $boards[$uid]['id'] . '/koko.php?page=' . $page, null]);
		$requestsB[$read['key']] = $read;
		$read = $asRequest(['overboard', $base . '/' . $boards[$uid]['id'] . '/koko.php?mode=overboard&page=' . $page, null]);
		$requestsB[$read['key']] = $read;
	}
	foreach (array_slice($boards[$uid]['threads'], 0, $opt['hot']) as $thread) {
		$read = $asRequest(['thread', $base . '/' . $boards[$uid]['id'] . '/koko.php?res=' . $thread['post_op_number'], null]);
		$requestsB[$read['key']] = $read;
	}
}
$wrongB = $verifyAtRest($requestsB);

foreach ($sessions as $session) {
	@unlink($session['jar']);
}

// posters on different boards share the posts table, so a uid guessed before the insert is not
// theirs alone: every thread this run opened must point at its own OP
$orphans = $pdo->prepare("SELECT t.thread_uid, t.post_op_post_uid FROM {$threadTable} t
	JOIN {$postTable} op ON op.thread_uid = t.thread_uid AND op.is_op = 1
	WHERE op.com LIKE ? AND op.post_uid <> t.post_op_post_uid");
$orphans->execute([$runId . ' thread %']);
$wrongOps = array_map(static fn(array $row): string => "thread {$row['thread_uid']} points at post {$row['post_op_post_uid']}", $orphans->fetchAll());
$opened = $pdo->prepare("SELECT COUNT(*) FROM {$postTable} WHERE is_op = 1 AND com LIKE ?");
$opened->execute([$runId . ' thread %']);
echo '  threads opened: ' . $opened->fetchColumn() . "\n";

// ---- Phase C: a change landing while its thread is being drawn -----------------

/** Start the reads, start the write $delay seconds later, and see them all through. */
function raceOnce(array $reads, array $write, float $delay): void {
	$queue = $reads;
	$writeAt = null;
	runTraffic(count($reads) + 1, static function () use (&$queue, &$writeAt, $write, $delay): ?array {
		if ($queue !== []) {
			return array_shift($queue);
		}
		if ($writeAt === null) {
			$writeAt = microtime(true) + $delay;
			usleep((int)($delay * 1e6));
			return $write;
		}
		return null;
	}, static function (): void {});
}

$wrongC = [];
$raced = 0;
for ($trial = 0; $trial < $opt['races']; $trial++) {
	$board = $boards[$pick($hotBoards)];
	$candidates = array_values(array_filter(array_slice($board['threads'], 0, $opt['hot']), static fn(array $t): bool => !empty($t['votable'])));
	if ($candidates === []) {
		continue;
	}
	$thread = $pick($candidates);
	$url = $base . '/' . $board['id'] . '/koko.php';
	$reads = [$asRequest(['index', $url . '?page=1', null]), $asRequest(['thread', $url . '?res=' . $thread['post_op_number'], null]), $asRequest(['overboard', $url . '?mode=overboard&page=1', null])];
	$vote = ['kind' => 'vote', 'url' => $url . '?mode=module&load=soudane&postUid=' . $thread['votable'][0] . '&type=yeah', 'cookie' => null, 'ajax' => true, 'key' => 'vote'];

	$clearFragments();
	$delay = mt_rand(0, 400) / 1000;
	raceOnce($reads, $vote, $delay);
	$raced++;
	foreach ($verifyAtRest($reads) as $wrong) {
		$wrongC[] = sprintf('vote %d ms into the draw: %s', $delay * 1000, $wrong);
	}
}
echo "\nPhase C: $raced votes timed to land while their thread's page was being drawn\n";

// ---- Verdict -----------------------------------------------------------------

$checks = [
	'every response is whole and free of errors' => array_map(static fn(string $what, int $n): string => "$what x$n", array_keys($failures), $failures),
	'phase A: the same page is the same every time' => $unstable,
	'phase A: the cache serves what a fresh render draws' => $wrongA,
	'phase B: nothing stale is served once the writers stop' => $wrongB,
	'phase B: every thread opened points at its own OP' => $wrongOps,
	'phase C: a change landing mid-draw does not leave the old markup behind' => $wrongC,
];
echo "\n";
$failed = false;
foreach ($checks as $name => $problems) {
	echo ($problems === [] ? '  ok    ' : '  FAIL  ') . $name . ($problems === [] ? '' : ' (' . count($problems) . ')') . "\n";
	foreach (array_slice($problems, 0, 6) as $problem) {
		echo '          ' . $problem . "\n";
	}
	$failed = $failed || $problems !== [];
}
echo "\n(" . count($requestsByKey) . ' pages compared after phase A, ' . count($requestsB) . " after phase B)\n";

exit($failed ? 1 : 0);
