<?php

/**
 * Load test for text quote lookups (the post API's pageName=quote), against a running install.
 *
 * Hovering a text quote can put a request on the server for something that used to be answered
 * from the page, so the question this asks is not whether the answer is right - the integration
 * test does that - but what the answer costs when a board full of readers hovers at once.
 *
 * Phase A  warms up and records every lookup's answer, one at a time.
 * Phase B  replays them all at once, and every answer must be the one phase A recorded: a
 *          lookup is a pure read, so concurrency may not change it.
 * Phase C  the worst case on purpose - needles that match nothing, on the longest threads, so
 *          the search runs its whole window and gives up.
 * Phase D  hostile parameters: overlong, wildcard, binary, negative, missing, repeated.
 * Phase F  one reader hovering several quotes at once, with and without their session cookie:
 *          a session's lock is held for the whole request, so this is where lookups would be
 *          answered one after another instead of together.
 * Phase E  ordinary page loads timed alone and again while the lookups run flat out, which is
 *          what "does this strain the site" actually means.
 *
 * It reports latency percentiles, throughput and database statements per lookup, and fails on a
 * 5xx, on an answer that changed under load, on a lookup that returns a post it should not, or
 * when a limit passed in is exceeded.
 *
 * Usage (the install under test must point at a koko_test* / koko_bench* database):
 *   KOKO_TEST_DSN=... KOKO_TEST_USER=... KOKO_TEST_PASS=... \
 *   php tests/stress/quoteLookups.php --base=http://127.0.0.1:8099
 *     --boards=b,g,lit       Board identifiers to draw threads from (default: every listed one)
 *     --threads=40           Threads sampled for the lookup corpus
 *     --seconds=20           Phase B duration
 *     --concurrency=24       Requests in flight
 *     --misses=400           Phase C requests
 *     --max-miss-ms=1500     Fail if a worst-case miss is slower than this (p99)
 *     --max-statements=10    Fail if a lookup averages more database statements than this
 *     --max-slowdown=3       Fail if a page waits longer than this beside the lookups
 *     --max-session-cost=2   Fail if a lookup costs more than this many page loads
 *     --seed=12345
 *
 * Exit code is 0 when every invariant held, 1 otherwise.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__, 2);

require_once $root . '/autoload.php';
require_once $root . '/code/Kokonotsuba/constants.php';

use Kokonotsuba\post\textFormat;
use Kokonotsuba\quote_link\textQuoteMatcher;

$opt = [
	'base' => '', 'boards' => '', 'threads' => 40, 'seconds' => 20, 'concurrency' => 24,
	'misses' => 400, 'max-miss-ms' => 1500, 'max-statements' => 10, 'max-slowdown' => 3,
	'max-session-cost' => 2,
	'seed' => random_int(1, PHP_INT_MAX >> 8),
];

foreach (array_slice($argv, 1) as $arg) {
	if (!preg_match('/^--([a-z-]+)=(.+)$/', $arg, $m) || !array_key_exists($m[1], $opt)) {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(2);
	}
	$opt[$m[1]] = is_int($opt[$m[1]]) ? (int)$m[2] : $m[2];
}

$base = rtrim($opt['base'], '/');

if ($base === '') {
	exit("Needs --base=URL of a running install.\n");
}

$dsn = getenv('KOKO_TEST_DSN') ?: '';

if (!preg_match('/dbname=(koko_(?:test|bench)\w*)/', $dsn)) {
	exit("KOKO_TEST_DSN must name a koko_test* or koko_bench* database.\n");
}

$pdo = new PDO($dsn, getenv('KOKO_TEST_USER') ?: '', getenv('KOKO_TEST_PASS') ?: '', [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tables = require $root . '/tables.php';
[$postTable, $threadTable, $boardTable, $fileTable, $deletedTable] =
	[$tables['POST_TABLE'], $tables['THREAD_TABLE'], $tables['BOARD_TABLE'], $tables['FILE_TABLE'], $tables['DELETED_POSTS_TABLE']];

mt_srand($opt['seed']);

const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0';

$problems = [];
$note = static function (string $problem) use (&$problems): void {
	$problems[] = $problem;
	echo "    \033[31m!\033[0m {$problem}\n";
};

echo "quote lookup load test: seed={$opt['seed']}\n";

// ---- HTTP -------------------------------------------------------------------

/** One request, blocking. Returns [status, body, seconds]. */
function fetchOne(string $url, string $cookie = ''): array {
	$handle = curl_init($url);
	curl_setopt_array($handle, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT => USER_AGENT,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HEADER => true,
	]);

	if ($cookie !== '') {
		curl_setopt($handle, CURLOPT_COOKIE, $cookie);
	}

	$response = (string)curl_exec($handle);
	$status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
	$time = (float)curl_getinfo($handle, CURLINFO_TOTAL_TIME);
	$headerSize = (int)curl_getinfo($handle, CURLINFO_HEADER_SIZE);
	curl_close($handle);

	return [$status, substr($response, $headerSize), $time, substr($response, 0, $headerSize)];
}

/**
 * Run URLs with a fixed number in flight.
 *
 * @param callable $each fn(string $url, int $status, string $body, float $seconds): void
 */
function fetchMany(array $urls, int $concurrency, callable $each, float $deadline = 0.0, string $cookie = ''): void {
	$multi = curl_multi_init();
	$running = [];
	$queue = $urls;

	$start = static function () use (&$queue, &$running, $multi, $deadline, $cookie): bool {
		$url = ($deadline > 0 && microtime(true) > $deadline) ? null : array_shift($queue);

		if ($url === null) {
			return false;
		}

		$handle = curl_init($url);
		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => USER_AGENT,
			CURLOPT_TIMEOUT => 60,
		]);

		if ($cookie !== '') {
			curl_setopt($handle, CURLOPT_COOKIE, $cookie);
		}

		curl_multi_add_handle($multi, $handle);
		$running[(int)$handle] = $url;

		return true;
	};

	for ($i = 0; $i < $concurrency; $i++) {
		if (!$start()) {
			break;
		}
	}

	// nothing in flight and nothing startable means the queue is spent or the deadline passed,
	// so this never outlives the work it was given
	while ($running) {
		curl_multi_exec($multi, $active);

		if (curl_multi_select($multi, 0.05) === -1) {
			usleep(1000);
		}

		while ($done = curl_multi_info_read($multi)) {
			$handle = $done['handle'];
			$url = $running[(int)$handle] ?? '';
			unset($running[(int)$handle]);

			$each(
				$url,
				(int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
				(string)curl_multi_getcontent($handle),
				(float)curl_getinfo($handle, CURLINFO_TOTAL_TIME)
			);

			curl_multi_remove_handle($multi, $handle);
			curl_close($handle);
			$start();
		}
	}

	curl_multi_close($multi);
}

function percentile(array $values, float $q): float {
	if (!$values) {
		return 0.0;
	}

	sort($values);
	return $values[(int)max(0, min(count($values) - 1, ceil($q * count($values)) - 1))];
}

function summarize(string $label, array $times, float $wall = 0.0): void {
	if (!$times) {
		echo "    {$label}: nothing recorded\n";
		return;
	}

	printf(
		"    %-28s n=%-6d p50=%5.0fms  p95=%5.0fms  p99=%5.0fms  max=%6.0fms%s\n",
		$label, count($times),
		percentile($times, 0.5) * 1000, percentile($times, 0.95) * 1000,
		percentile($times, 0.99) * 1000, max($times) * 1000,
		$wall > 0 ? sprintf('  %.0f req/s', count($times) / $wall) : ''
	);
}

// ---- What there is to look up ----------------------------------------------

$boardFilter = $opt['boards'] === '' ? [] : array_map('trim', explode(',', $opt['boards']));
$boards = [];

foreach ($pdo->query("SELECT board_uid, board_identifier FROM `{$boardTable}` WHERE listed = 1 AND board_uid > 0")->fetchAll() as $row) {
	if (!$boardFilter || in_array($row['board_identifier'], $boardFilter, true)) {
		$boards[(int)$row['board_uid']] = $row['board_identifier'];
	}
}

if (!$boards) {
	exit("No listed boards to test.\n");
}

$apiUrl = static fn(string $board): string => "/{$board}/koko.php?mode=module&load=postApi";

$quoteUrl = static function (string $board, string $threadUid, int $beforeUid, string $text, bool $quoted = false) use ($apiUrl, $base): string {
	return $base . $apiUrl($board) . '&pageName=quote'
		. '&thread_uid=' . rawurlencode($threadUid)
		. '&before_uid=' . $beforeUid
		. '&quoted=' . ($quoted ? 1 : 0)
		. '&text=' . rawurlencode($text);
};

echo "  sampling threads from " . count($boards) . " board(s)\n";

$threadRows = $pdo->query("
	SELECT t.thread_uid, t.boardUID, COUNT(p.post_uid) AS posts
	FROM `{$threadTable}` t
	INNER JOIN `{$postTable}` p ON p.thread_uid = t.thread_uid
	WHERE t.boardUID IN (" . implode(',', array_keys($boards)) . ")
	  AND NOT EXISTS (SELECT 1 FROM `{$deletedTable}` WHERE open_key = t.post_op_post_uid)
	GROUP BY t.thread_uid, t.boardUID
	HAVING posts > 3
	ORDER BY posts DESC
	LIMIT " . max(1, $opt['threads'] * 3)
)->fetchAll();

if (!$threadRows) {
	exit("No threads with replies to sample.\n");
}

shuffle($threadRows);
$threadRows = array_slice($threadRows, 0, $opt['threads']);

/** The longest threads, where a search that finds nothing does the most work. */
$longestThreads = $pdo->query("
	SELECT t.thread_uid, t.boardUID, COUNT(p.post_uid) AS posts
	FROM `{$threadTable}` t
	INNER JOIN `{$postTable}` p ON p.thread_uid = t.thread_uid
	WHERE t.boardUID IN (" . implode(',', array_keys($boards)) . ")
	GROUP BY t.thread_uid, t.boardUID
	ORDER BY posts DESC
	LIMIT 5
")->fetchAll();

$lookups = [];
$postsOfThread = $pdo->prepare("
	SELECT p.post_uid, p.no, p.is_op, p.com, p.text_format,
	       (SELECT CONCAT(f.file_name, '|', f.file_ext) FROM `{$fileTable}` f WHERE f.post_uid = p.post_uid AND f.is_hidden = 0 LIMIT 1) AS file
	FROM `{$postTable}` p
	WHERE p.thread_uid = :thread
	ORDER BY p.is_op DESC, p.post_uid ASC
	LIMIT 400
");

foreach ($threadRows as $thread) {
	$board = $boards[(int)$thread['boardUID']];
	$postsOfThread->execute([':thread' => $thread['thread_uid']]);
	$posts = $postsOfThread->fetchAll();

	if (count($posts) < 2) {
		continue;
	}

	// a quote of a real line of an earlier post: what a reader actually hovers
	for ($attempt = 0; $attempt < 4; $attempt++) {
		$sourceIndex = mt_rand(0, count($posts) - 2);
		$source = $posts[$sourceIndex];
		$quoter = $posts[mt_rand($sourceIndex + 1, count($posts) - 1)];
		$lines = textQuoteMatcher::lines((string)$source['com'], textFormat::fromStored($source['text_format']));

		foreach ($lines as $line) {
			$needle = textQuoteMatcher::normalizeNeedle(ltrim($line, '>＞'));

			if ($needle === null || mb_strlen($needle) < 8 || textQuoteMatcher::postNumber($needle) !== null) {
				continue;
			}

			$lookups[] = ['kind' => 'text', 'url' => $quoteUrl($board, $thread['thread_uid'], (int)$quoter['post_uid'], $needle), 'needle' => $needle, 'thread' => $thread['thread_uid']];
			break 2;
		}
	}

	// a file name, and a post number, and a needle nothing holds
	foreach ($posts as $entry) {
		if ($entry['file'] !== null && $entry['post_uid'] !== $posts[count($posts) - 1]['post_uid']) {
			[$name, $ext] = explode('|', (string)$entry['file']);
			$lookups[] = [
				'kind' => 'file',
				'url' => $quoteUrl($board, $thread['thread_uid'], (int)$posts[count($posts) - 1]['post_uid'], textQuoteMatcher::displayedFileName($name, $ext)),
				'needle' => textQuoteMatcher::displayedFileName($name, $ext),
				'thread' => $thread['thread_uid'],
			];
			break;
		}
	}

	$lookups[] = [
		'kind' => 'number',
		'url' => $quoteUrl($board, $thread['thread_uid'], (int)$posts[count($posts) - 1]['post_uid'], 'No.' . $posts[0]['no']),
		'needle' => 'No.' . $posts[0]['no'],
		'thread' => $thread['thread_uid'],
	];

	$lookups[] = [
		'kind' => 'miss',
		'url' => $quoteUrl($board, $thread['thread_uid'], (int)$posts[count($posts) - 1]['post_uid'], 'no post has ever said this ' . mt_rand()),
		'needle' => '',
		'thread' => $thread['thread_uid'],
	];
}

if (!$lookups) {
	exit("Could not build a lookup corpus from these boards.\n");
}

echo '  ' . count($lookups) . " lookups in the corpus (" . implode(', ', array_map(
	static fn(string $kind): string => $kind . ': ' . count(array_filter($lookups, static fn(array $l): bool => $l['kind'] === $kind)),
	['text', 'file', 'number', 'miss']
)) . ")\n";

// ---- Invariants on one answer ----------------------------------------------

/** Everything that must be true of a single response, whatever it answers. */
$checkResponse = static function (array $lookup, int $status, string $body) use ($note): ?array {
	$where = $lookup['kind'] . ' lookup ' . var_export($lookup['needle'], true);

	if ($status >= 500) {
		$note("{$where}: HTTP {$status}");
		return null;
	}

	if (!in_array($status, [200, 400, 404], true)) {
		$note("{$where}: unexpected HTTP {$status}");
		return null;
	}

	if (str_contains($body, '<html') || str_contains($body, 'There has been an error')) {
		$note("{$where}: answered with an error page");
		return null;
	}

	$data = json_decode($body, true);

	if ($status !== 200) {
		if (!is_string($data) && !is_array($data)) {
			$note("{$where}: HTTP {$status} body is not JSON");
		}
		return null;
	}

	if (!is_array($data) || !isset($data['html'], $data['post_uid'], $data['parent_thread_uid'])) {
		$note("{$where}: 200 without a post payload");
		return null;
	}

	if (!str_contains((string)$data['html'], 'class="post')) {
		$note("{$where}: html holds no rendered post");
		return null;
	}

	if ((string)$data['parent_thread_uid'] !== $lookup['thread']) {
		$note("{$where}: answered with a post from thread {$data['parent_thread_uid']}");
		return null;
	}

	// the post it found must genuinely hold what was asked for
	if ($lookup['kind'] === 'text' || $lookup['kind'] === 'file') {
		$format = textFormat::fromStored($data['text_format'] ?? 1);
		$holdsText = textQuoteMatcher::commentMatches((string)$data['comment'], $format, $lookup['needle'], false);
		$holdsFile = false;

		foreach ($data['attachments'] ?? [] as $attachment) {
			if (textQuoteMatcher::displayedFileName((string)$attachment['file_name'], (string)$attachment['file_extension']) === $lookup['needle']) {
				$holdsFile = true;
			}
		}

		if (!$holdsText && !$holdsFile) {
			$note("{$where}: post {$data['post_uid']} holds neither the text nor a file of that name");
			return null;
		}
	}

	return $data;
};

// ---- Phase A: one at a time ------------------------------------------------

echo "\n  phase A: every lookup once, sequentially\n";

$selects = static function () use ($pdo): int {
	return (int)$pdo->query("SHOW GLOBAL STATUS LIKE 'Com_select'")->fetch()['Value'];
};

$expected = [];
$timesByKind = ['text' => [], 'file' => [], 'number' => [], 'miss' => []];
$statementsBefore = $selects();
$phaseAStart = microtime(true);

foreach ($lookups as $lookup) {
	[$status, $body, $time, $headers] = fetchOne($lookup['url']);
	$data = $checkResponse($lookup, $status, $body);
	$expected[$lookup['url']] = [$status, $data['post_uid'] ?? null];
	$timesByKind[$lookup['kind']][] = $time;

	if ($status === 200 && !preg_match('/Cache-Control:.*max-age=[1-9]/i', $headers)) {
		$note('a hit came back without a cache window: ' . trim((string)strstr($headers, 'Cache-Control')));
	}
}

$phaseAWall = microtime(true) - $phaseAStart;
$statementsRun = $selects() - $statementsBefore - count($lookups);   // one SHOW per reading
$perLookup = count($lookups) > 0 ? $statementsRun / count($lookups) : 0;

foreach ($timesByKind as $kind => $times) {
	summarize($kind, $times);
}

printf("    %-28s %.1f\n", 'database statements/lookup', $perLookup);

if ($perLookup > $opt['max-statements']) {
	$note(sprintf('a lookup costs %.1f statements, over the %d allowed', $perLookup, $opt['max-statements']));
}

$hits = count(array_filter($expected, static fn(array $e): bool => $e[0] === 200));
echo "    {$hits} of " . count($expected) . " lookups found a post\n";

// ---- Phase E1: pages on a quiet server -------------------------------------

$pageUrls = [];

foreach ($boards as $board) {
	$pageUrls[] = $base . "/{$board}/koko.php";
}

foreach (array_slice($threadRows, 0, 8) as $thread) {
	$row = $pdo->query("SELECT post_op_number FROM `{$threadTable}` WHERE thread_uid = " . $pdo->quote($thread['thread_uid']))->fetch();
	$pageUrls[] = $base . '/' . $boards[(int)$thread['boardUID']] . '/koko.php?res=' . (int)$row['post_op_number'];
}

echo "\n  phase E: ordinary pages, quiet server\n";
$quietPageTimes = [];

foreach ($pageUrls as $url) {
	[$status, , $time] = fetchOne($url);
	$quietPageTimes[] = $time;

	if ($status >= 500) {
		$note("page {$url}: HTTP {$status}");
	}
}

summarize('pages alone', $quietPageTimes);

// ---- Phase B: all at once, for a while -------------------------------------

echo "\n  phase B: the same lookups at once for {$opt['seconds']}s, {$opt['concurrency']} in flight\n";

$byUrl = [];
foreach ($lookups as $lookup) {
	$byUrl[$lookup['url']] = $lookup;
}

$replay = [];
$deadline = microtime(true) + $opt['seconds'];

// enough copies of the corpus to keep the server busy for the duration
while (count($replay) < max(200, $opt['concurrency'] * 40)) {
	foreach ($lookups as $lookup) {
		$replay[] = $lookup['url'];
	}
}
shuffle($replay);

$loadTimes = [];
$changed = 0;
$statementsBefore = $selects();
$phaseBStart = microtime(true);

fetchMany($replay, $opt['concurrency'], function (string $url, int $status, string $body, float $time)
		use ($byUrl, $expected, $checkResponse, &$loadTimes, &$changed, $note, $deadline): void {
	$loadTimes[] = $time;
	$lookup = $byUrl[$url] ?? null;

	if ($lookup === null) {
		return;
	}

	$data = $checkResponse($lookup, $status, $body);
	[$wasStatus, $wasUid] = $expected[$url] ?? [null, null];

	if ($status !== $wasStatus || ($data['post_uid'] ?? null) !== $wasUid) {
		$changed++;

		if ($changed <= 3) {
			$note("{$lookup['kind']} lookup answered {$status}/" . var_export($data['post_uid'] ?? null, true)
				. " under load, but {$wasStatus}/" . var_export($wasUid, true) . " on its own");
		}
	}
});

$phaseBWall = microtime(true) - $phaseBStart;
$statementsRun = $selects() - $statementsBefore - 1;
summarize('under load', $loadTimes, $phaseBWall);
printf("    %-28s %.1f\n", 'database statements/lookup', count($loadTimes) ? $statementsRun / count($loadTimes) : 0);

if ($changed > 0) {
	$note("{$changed} answers changed under load");
}

// ---- Phase E2: pages while the lookups run ---------------------------------

echo "\n  phase E: the same pages while lookups run flat out\n";

// the pages go through the same queue as the lookups, so they really do compete for the workers
$mixed = [];
$pageSlot = 0;

while (count($mixed) < 6000) {
	foreach ($lookups as $lookup) {
		$mixed[] = $lookup['url'];

		if (count($mixed) % 12 === 0) {
			$mixed[] = $pageUrls[$pageSlot++ % count($pageUrls)];
		}
	}
}

$pageUrlSet = array_fill_keys($pageUrls, true);
$busyPageTimes = [];
$busyLookupTimes = [];

fetchMany(
	$mixed,
	$opt['concurrency'],
	function (string $url, int $status, string $body, float $time)
			use ($pageUrlSet, &$busyPageTimes, &$busyLookupTimes, $note): void {
		if (isset($pageUrlSet[$url])) {
			$busyPageTimes[] = $time;

			if ($status >= 500) {
				$note("page under load: HTTP {$status}");
			}

			return;
		}

		$busyLookupTimes[] = $time;

		if ($status >= 500) {
			$note("lookup under load: HTTP {$status}");
		}
	},
	microtime(true) + max(5, (int)round($opt['seconds'] / 2))
);

summarize('lookups beside the pages', $busyLookupTimes);
summarize('pages under load', $busyPageTimes);

// at full tilt everything queues, so what matters is whether a page waits longer than the
// lookups it is queued behind: that is what a lookup holding something would look like
$quietMedian = percentile($quietPageTimes, 0.5);
$busyPageMedian = percentile($busyPageTimes, 0.5);
$busyLookupMedian = percentile($busyLookupTimes, 0.5);
$share = $busyLookupMedian > 0 ? $busyPageMedian / $busyLookupMedian : 0;

printf("    %-28s %.2fx (a page against a lookup beside it)\n", 'page share of the queue', $share);
printf("    %-28s %.2fx (a saturated server slows everything)\n", 'pages vs a quiet server', $quietMedian > 0 ? $busyPageMedian / $quietMedian : 0);

if ($share > $opt['max-slowdown']) {
	$note(sprintf('a page waits %.1fx as long as a lookup beside it, over the %dx allowed', $share, $opt['max-slowdown']));
}

// ---- Phase F: one reader's browser ------------------------------------------

echo "\n  phase F: one reader, hovering several quotes at once\n";

// a reader carries a session cookie, and PHP holds a session's lock for the whole request, so
// this is where lookups would be answered one after another instead of together
[, , , $pageHeaders] = fetchOne($base . '/' . reset($boards) . '/koko.php');
preg_match('/Set-Cookie: (kokonotsuba_session_id_[^;]+)/i', $pageHeaders, $cookieMatch);
$cookie = $cookieMatch[1] ?? '';

if ($cookie === '') {
	echo "    no session cookie was set; skipped\n";
} else {
	$readerUrls = [];

	while (count($readerUrls) < 60) {
		foreach ($lookups as $lookup) {
			$readerUrls[] = $lookup['url'];
		}
	}

	$readerUrls = array_slice($readerUrls, 0, 60);

	/** The same traffic, at the same concurrency, told apart only by the cookie. */
	$readerRun = static function (string $withCookie) use ($readerUrls, $note): array {
		$times = [];

		fetchMany($readerUrls, 6, function (string $url, int $status, string $body, float $time) use (&$times, $note): void {
			$times[] = $time;

			if ($status >= 500) {
				$note("a lookup from a reader answered HTTP {$status}");
			}
		}, 0.0, $withCookie);

		return $times;
	};

	/** The reader's own pages, six at once under the same session, as the yardstick. */
	$pageRun = static function (string $withCookie) use ($pageUrls, $note): array {
		$times = [];
		$urls = [];

		while (count($urls) < 24) {
			foreach ($pageUrls as $pageUrl) {
				$urls[] = $pageUrl;
			}
		}

		fetchMany(array_slice($urls, 0, 24), 6, function (string $url, int $status, string $body, float $time) use (&$times, $note): void {
			$times[] = $time;

			if ($status >= 500) {
				$note("a page answered HTTP {$status}");
			}
		}, 0.0, $withCookie);

		return $times;
	};

	$readerRun('');   // warm every path before any of them is timed
	$pageRun('');

	$anonTimes = $readerRun('');
	$sessionTimes = $readerRun($cookie);
	$sessionPageTimes = $pageRun($cookie);

	summarize('lookups as the page sends them', $anonTimes);
	summarize('lookups carrying a session', $sessionTimes);
	summarize('pages carrying a session', $sessionPageTimes);

	$anonMedian = percentile($anonTimes, 0.5);
	$sessionMedian = percentile($sessionTimes, 0.5);
	$pageMedian = percentile($sessionPageTimes, 0.5);

	// a session's lock is held for the whole request, whatever the request is, so the honest
	// question is whether a lookup costs a reader more than the pages they already load
	printf("    %-28s %.2fx\n", 'session vs no session', $anonMedian > 0 ? $sessionMedian / $anonMedian : 0);
	printf("    %-28s %.2fx\n", 'a lookup vs a page', $pageMedian > 0 ? $sessionMedian / $pageMedian : 0);

	if ($pageMedian > 0 && $sessionMedian / $pageMedian > $opt['max-session-cost']) {
		$note(sprintf("a lookup costs %.1fx a page load under the same session", $sessionMedian / $pageMedian));
	}
}

// ---- Phase C: the worst case -----------------------------------------------

echo "\n  phase C: needles nothing holds, on the longest threads\n";

$missUrls = [];

foreach ($longestThreads as $thread) {
	$board = $boards[(int)$thread['boardUID']] ?? null;

	if ($board === null) {
		continue;
	}

	$newest = (int)$pdo->query("SELECT MAX(post_uid) FROM `{$postTable}` WHERE thread_uid = " . $pdo->quote($thread['thread_uid']))->fetchColumn();

	for ($i = 0; $i < max(1, (int)($opt['misses'] / max(1, count($longestThreads)))); $i++) {
		$missUrls[] = $quoteUrl($board, $thread['thread_uid'], $newest + 1, 'nothing in this thread says ' . mt_rand(), mt_rand(0, 1) === 1);
	}

	echo "    {$board} thread {$thread['thread_uid']} has {$thread['posts']} posts\n";
}

$missTimes = [];
$missStart = microtime(true);
$statementsBefore = $selects();

fetchMany($missUrls, $opt['concurrency'], function (string $url, int $status, string $body, float $time) use (&$missTimes, $note): void {
	$missTimes[] = $time;

	if ($status !== 404) {
		$note("a needle nothing holds answered HTTP {$status}");
	}
});

$statementsRun = $selects() - $statementsBefore - 1;
summarize('worst-case misses', $missTimes, microtime(true) - $missStart);
printf("    %-28s %.1f\n", 'database statements/lookup', count($missTimes) ? $statementsRun / count($missTimes) : 0);

if (percentile($missTimes, 0.99) * 1000 > $opt['max-miss-ms']) {
	$note(sprintf('a worst-case miss takes %.0fms at p99, over the %dms allowed', percentile($missTimes, 0.99) * 1000, $opt['max-miss-ms']));
}

// ---- Phase D: hostile parameters -------------------------------------------

echo "\n  phase D: hostile parameters\n";

$board = reset($boards);
$someThread = $threadRows[0]['thread_uid'];
$someUid = (int)$pdo->query("SELECT MAX(post_uid) FROM `{$postTable}` WHERE thread_uid = " . $pdo->quote($someThread))->fetchColumn();

$hostile = [
	'no parameters' => $base . $apiUrl($board) . '&pageName=quote',
	'empty text' => $quoteUrl($board, $someThread, $someUid, ''),
	'blank text' => $quoteUrl($board, $someThread, $someUid, "   \t "),
	'newline in text' => $quoteUrl($board, $someThread, $someUid, "a\nb"),
	'nul in text' => $base . $apiUrl($board) . "&pageName=quote&thread_uid={$someThread}&before_uid={$someUid}&text=a%00b",
	'invalid utf8' => $base . $apiUrl($board) . "&pageName=quote&thread_uid={$someThread}&before_uid={$someUid}&text=%FF%FE",
	'2KB of text' => $quoteUrl($board, $someThread, $someUid, str_repeat('ab', 1024)),
	'64KB of text' => $quoteUrl($board, $someThread, $someUid, str_repeat('c', 65536)),
	'only wildcards' => $quoteUrl($board, $someThread, $someUid, str_repeat('%_', 50)),
	'backslashes' => $quoteUrl($board, $someThread, $someUid, str_repeat('\\', 40)),
	'sql-ish' => $quoteUrl($board, $someThread, $someUid, "' OR 1=1 -- "),
	'quote-ish' => $quoteUrl($board, $someThread, $someUid, '" UNION SELECT com FROM posts #'),
	'negative before_uid' => $quoteUrl($board, $someThread, -5, 'cat'),
	'zero before_uid' => $quoteUrl($board, $someThread, 0, 'cat'),
	'huge before_uid' => $quoteUrl($board, $someThread, PHP_INT_MAX, 'cat'),
	'float before_uid' => $base . $apiUrl($board) . "&pageName=quote&thread_uid={$someThread}&before_uid=1.5e9&text=cat",
	'word before_uid' => $base . $apiUrl($board) . "&pageName=quote&thread_uid={$someThread}&before_uid=abc&text=cat",
	'unknown thread' => $quoteUrl($board, 'no-such-thread-uid', $someUid, 'cat'),
	'thread with a quote' => $quoteUrl($board, "' OR '1", $someUid, 'cat'),
	'400-char thread uid' => $quoteUrl($board, str_repeat('a', 400), $someUid, 'cat'),
	'repeated text param' => $quoteUrl($board, $someThread, $someUid, 'cat') . '&text=dog',
	'repeated pageName' => $quoteUrl($board, $someThread, $someUid, 'cat') . '&pageName=thread',
	'quoted is nonsense' => $quoteUrl($board, $someThread, $someUid, 'cat') . '&quoted=yes-please',
	'post number as text' => $quoteUrl($board, $someThread, $someUid, '999999999999999999999999'),
	'astral plane' => $quoteUrl($board, $someThread, $someUid, str_repeat('😀', 200)),
	'rtl override' => $quoteUrl($board, $someThread, $someUid, "\u{202E}gnj.exe"),
	'html in text' => $quoteUrl($board, $someThread, $someUid, '<script>alert(1)</script>'),
];

$hostileTimes = [];
$slowest = ['', 0.0];

// warm the worker up so the first case is not charged for it
fetchOne($base . $apiUrl($board) . '&pageName=quote');

foreach ($hostile as $label => $url) {
	[$status, $body, $time] = fetchOne($url);
	$hostileTimes[] = $time;

	if ($time > $slowest[1]) {
		$slowest = [$label, $time];
	}

	if ($status >= 500) {
		$note("hostile '{$label}': HTTP {$status}");
	}

	if (str_contains($body, '<html') || str_contains($body, 'There has been an error')) {
		$note("hostile '{$label}': answered with an error page");
	}

	if ($status === 200 && str_contains($body, '<script>alert(1)</script>')) {
		$note("hostile '{$label}': the needle came back unescaped");
	}

	if ($time > 5) {
		$note(sprintf("hostile '%s': took %.1fs", $label, $time));
	}
}

summarize('hostile parameters', $hostileTimes);
printf("    %-28s %s (%.0fms)\n", 'slowest case', $slowest[0], $slowest[1] * 1000);

// ---- Report -----------------------------------------------------------------

echo "\n";

if ($problems) {
	echo "\033[31m" . count($problems) . " problem(s)\033[0m — rerun with --seed={$opt['seed']}\n";
	exit(1);
}

echo "\033[32mNo problems found.\033[0m Rerun this exact traffic with --seed={$opt['seed']}\n";
exit(0);
