<?php

/**
 * Traffic fuzzer for the thread fragment cache.
 *
 * tests/fuzz/rebuild.php checks the cache one call at a time. This forks workers that read, draw,
 * reply, edit and clear against one cache directory at once, the way concurrent requests do, and
 * checks what only shows under contention: torn or foreign reads, leaked temp files, and
 * fragments that are still wrong once the traffic has stopped.
 *
 * The database is stood in for by one locked file per thread holding "count:rev". A reply bumps
 * both (the stamp changes), an edit bumps rev alone (it does not), and both forget the thread
 * after "committing", which is the protocol threadFragments asks of its callers. A reader takes
 * the stamp from one read and the content from a later one, as a page takes them from two queries.
 *
 * Usage:
 *   php tests/stress/threadFragmentCache.php                 8 workers, 5 seconds
 *   php tests/stress/threadFragmentCache.php --workers=16 --seconds=30
 *   php tests/stress/threadFragmentCache.php --threads=6     Fewer threads, more contention
 *   php tests/stress/threadFragmentCache.php --render-ms=20  Longer draws, wider race windows
 *   php tests/stress/threadFragmentCache.php --seed=12345
 *
 * Exit code is 0 when every invariant held, 1 otherwise.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}
if (!function_exists('pcntl_fork')) {
	exit("Needs the pcntl extension.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use Kokonotsuba\cache\thread_fragment\threadFragmentCache;

$opt = ['workers' => 8, 'seconds' => 5, 'threads' => 24, 'render-ms' => 3, 'seed' => random_int(1, PHP_INT_MAX >> 8)];
foreach (array_slice($argv, 1) as $arg) {
	if (!preg_match('/^--([a-z-]+)=(\d+)$/', $arg, $m) || !isset($opt[$m[1]])) {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(2);
	}
	$opt[$m[1]] = (int)$m[2];
}

$scratch = sys_get_temp_dir() . '/koko-stress-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '/';
$cacheDir = $scratch . 'cache/threads/';
$truthDir = $scratch . 'truth/';
mkdir($truthDir, 0755, true);

$threadUids = [];
for ($i = 0; $i < $opt['threads']; $i++) {
	$threadUids[] = sprintf('%016x', $i + 1);
	file_put_contents($truthDir . $threadUids[$i], '1:0');
}
$variants = ['index-5', 'overboard-1-5', 'overboard-2-5', 'thread-1', 'thread-2', 'thread-all'];
$hostileKeys = ['', '.', '..', '../../etc/passwd', '*', 'a.*.html', "x\0y", str_repeat('k', 300), '/', '%00', "new\nline"];

/** A thread's "row": [count, rev]. Shared lock, so a read never sees half a commit. */
function readTruth(string $truthDir, string $uid): array {
	$fh = fopen($truthDir . $uid, 'r');
	flock($fh, LOCK_SH);
	$raw = stream_get_contents($fh);
	flock($fh, LOCK_UN);
	fclose($fh);
	return array_map('intval', explode(':', $raw));
}

/** Commit a change to a thread's row. */
function writeTruth(string $truthDir, string $uid, callable $change): void {
	$fh = fopen($truthDir . $uid, 'c+');
	flock($fh, LOCK_EX);
	[$count, $rev] = array_map('intval', explode(':', stream_get_contents($fh)));
	[$count, $rev] = $change($count, $rev);
	ftruncate($fh, 0);
	rewind($fh);
	fwrite($fh, $count . ':' . $rev);
	fflush($fh);
	flock($fh, LOCK_UN);
	fclose($fh);
}

/** Markup that says what it is, so a read can tell whether it got the whole of the right thing. */
function drawFragment(string $uid, string $variant, string $stamp, int $count, int $rev): string {
	$body = str_repeat(chr(97 + mt_rand(0, 25)), mt_rand(0, 3) === 0 ? mt_rand(60000, 300000) : mt_rand(0, 4000));
	return implode('|', [bin2hex($uid), bin2hex($variant), bin2hex($stamp), $count, $rev, sha1($body)]) . "\n" . $body;
}

/** @return array{count:int,rev:int}|string The fragment's header, or what is wrong with it. */
function checkFragment(string $html, string $uid, string $variant, string $stamp): array|string {
	$parts = explode("\n", $html, 2);
	$head = explode('|', $parts[0]);
	if (count($parts) !== 2 || count($head) !== 6) {
		return 'unreadable header (' . strlen($html) . ' bytes)';
	}
	// keys are hex in the header, since a hostile one may hold the separators
	if ($head[0] !== bin2hex($uid) || $head[1] !== bin2hex($variant) || $head[2] !== bin2hex($stamp)) {
		return 'foreign fragment: asked ' . json_encode([$uid, $variant, $stamp], JSON_INVALID_UTF8_SUBSTITUTE) . ', got ' . json_encode(array_map('hex2bin', array_slice($head, 0, 3)), JSON_INVALID_UTF8_SUBSTITUTE);
	}
	if (sha1($parts[1]) !== $head[5]) {
		return 'torn body (' . strlen($parts[1]) . ' bytes)';
	}
	return ['count' => (int)$head[3], 'rev' => (int)$head[4]];
}

$stampOf = static fn(int $count): string => $count . '-20260918120000';

// ---- Workers ----------------------------------------------------------------

$worker = static function (int $id) use ($opt, $cacheDir, $truthDir, $threadUids, $variants, $hostileKeys, $stampOf, $scratch): void {
	mt_srand($opt['seed'] + $id);
	$cache = new threadFragmentCache($cacheDir);
	$stats = ['ops' => 0, 'hits' => 0, 'misses' => 0, 'puts' => 0, 'putsFailed' => 0, 'replies' => 0, 'edits' => 0, 'clears' => 0, 'staleHits' => 0, 'hostile' => 0, 'failures' => []];
	$fail = static function (string $what) use (&$stats): void {
		if (count($stats['failures']) < 20) {
			$stats['failures'][] = $what;
		}
	};
	$deadline = microtime(true) + $opt['seconds'];

	while (microtime(true) < $deadline) {
		$stats['ops']++;
		$uid = $threadUids[mt_rand(0, count($threadUids) - 1)];
		$variant = $variants[mt_rand(0, count($variants) - 1)];
		$roll = mt_rand(1, 1000);

		try {
			if ($roll <= 780) {
				// a reader: the row gives the stamp, the posts are fetched afterwards
				[$count, $rev] = readTruth($truthDir, $uid);
				$stamp = $stampOf($count);
				$html = $cache->get($uid, $variant, $stamp);
				if ($html !== null) {
					$stats['hits']++;
					$checked = checkFragment($html, $uid, $variant, $stamp);
					if (is_string($checked)) {
						$fail('get: ' . $checked);
					} elseif ($checked['rev'] < $rev) {
						// older than a change committed before this read began
						$stats['staleHits']++;
					}
					continue;
				}
				$stats['misses']++;
				[$drawnCount, $drawnRev] = readTruth($truthDir, $uid);
				usleep(mt_rand(0, $opt['render-ms'] * 1000));
				$cache->put($uid, $variant, $stamp, drawFragment($uid, $variant, $stamp, $drawnCount, $drawnRev))
					? $stats['puts']++
					: $stats['putsFailed']++;
			} elseif ($roll <= 850) {
				// a reply or a deletion: the stamp moves
				writeTruth($truthDir, $uid, static fn(int $c, int $r): array => [max(1, $c + (mt_rand(0, 3) === 0 ? -1 : 1)), $r + 1]);
				$cache->forget($uid);
				$stats['replies']++;
			} elseif ($roll <= 985) {
				// an edit, a vote, a flag: the stamp stays where it is
				writeTruth($truthDir, $uid, static fn(int $c, int $r): array => [$c, $r + 1]);
				$cache->forget($uid);
				$stats['edits']++;
			} elseif ($roll <= 988) {
				// a config save
				foreach ($threadUids as $each) {
					writeTruth($truthDir, $each, static fn(int $c, int $r): array => [$c, $r + 1]);
				}
				$cache->clear();
				$stats['clears']++;
			} else {
				// keys no thread has
				$stats['hostile']++;
				$key = $hostileKeys[mt_rand(0, count($hostileKeys) - 1)];
				$other = $hostileKeys[mt_rand(0, count($hostileKeys) - 1)];
				$html = drawFragment($key, $other, $key, 0, 0);
				if ($cache->put($key, $other, $key, $html) && ($got = $cache->get($key, $other, $key)) !== null && is_string($why = checkFragment($got, $key, $other, $key))) {
					$fail('hostile get: ' . $why);
				}
				if (mt_rand(0, 1)) {
					$cache->forget($key);
				}
			}
		} catch (Throwable $e) {
			$fail(get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
		}
	}

	file_put_contents($scratch . 'worker-' . $id . '.json', json_encode($stats));
};

$started = microtime(true);
$pids = [];
for ($id = 0; $id < $opt['workers']; $id++) {
	$pid = pcntl_fork();
	if ($pid === 0) {
		$worker($id);
		exit(0);
	}
	$pids[] = $pid;
}
$crashed = 0;
foreach ($pids as $pid) {
	pcntl_waitpid($pid, $status);
	if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
		$crashed++;
	}
}
$elapsed = microtime(true) - $started;

// ---- After the traffic ------------------------------------------------------

$totals = [];
$failures = [];
foreach (glob($scratch . 'worker-*.json') as $file) {
	$stats = json_decode(file_get_contents($file), true);
	foreach ($stats as $key => $value) {
		if ($key === 'failures') {
			$failures = array_merge($failures, $value);
		} else {
			$totals[$key] = ($totals[$key] ?? 0) + $value;
		}
	}
}
if ($crashed > 0) {
	$failures[] = "$crashed worker(s) died";
}

// nothing is changing any more, so whatever is served now is served until the thread next changes
$cache = new threadFragmentCache($cacheDir);
$stale = [];
$served = 0;
foreach ($threadUids as $uid) {
	[$count, $rev] = readTruth($truthDir, $uid);
	foreach ($variants as $variant) {
		$html = $cache->get($uid, $variant, $stampOf($count));
		if ($html === null) {
			continue;
		}
		$served++;
		$checked = checkFragment($html, $uid, $variant, $stampOf($count));
		if (is_string($checked)) {
			$failures[] = 'at rest: ' . $checked;
		} elseif ($checked['rev'] !== $rev || $checked['count'] !== $count) {
			$stale[] = "$uid/$variant drawn from {$checked['count']}:{$checked['rev']}, thread is at $count:$rev";
		}
	}
}

$entries = is_dir($cacheDir) ? array_values(array_diff(scandir($cacheDir), ['.', '..', 'epochs'])) : [];
// epochs are sharded, so however many threads were forgotten there is a fixed handful of them
$epochFiles = is_dir($cacheDir . 'epochs') ? array_values(array_diff(scandir($cacheDir . 'epochs'), ['.', '..'])) : [];
$strayEpochs = array_values(array_filter($epochFiles, static fn(string $e): bool => !preg_match('/^(all|[0-9a-f]{2})$/', $e)));
$tempFiles = array_filter($entries, static fn(string $e): bool => str_ends_with($e, '.tmp'));
$misshapen = array_filter($entries, static fn(string $e): bool => !str_ends_with($e, '.tmp') && !preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.v\d+\.html$/', $e));
$perVariant = [];
foreach ($entries as $entry) {
	$parts = explode('.', $entry);
	$perVariant[$parts[0] . '.' . ($parts[1] ?? '')] = ($perVariant[$parts[0] . '.' . ($parts[1] ?? '')] ?? 0) + 1;
}
$orphans = array_sum(array_map(static fn(int $n): int => $n - 1, $perVariant));
$escaped = array_values(array_diff(scandir($scratch), ['.', '..', 'cache', 'truth'], array_map('basename', glob($scratch . 'worker-*.json'))));

$cache->clear();
$afterClear = array_values(array_diff(scandir($cacheDir) ?: [], ['.', '..', 'epochs']));

$checks = [
	'no torn, foreign or failed reads' => $failures,
	'nothing stale is served once traffic stops' => $stale,
	'no temp files left behind' => array_values($tempFiles),
	'every file has the cache file shape' => array_values($misshapen),
	'epochs stay a bounded set of whole files' => count($epochFiles) > 257 ? ['more than 257: ' . count($epochFiles)] : $strayEpochs,
	'nothing written outside the cache directory' => $escaped,
	'clear empties the directory' => $afterClear,
];

printf("seed %d, %d workers, %d threads x %d variants, %.1fs\n", $opt['seed'], $opt['workers'], $opt['threads'], count($variants), $elapsed);
printf("%s ops (%s/s): %s hits, %s misses, %s puts (%s refused), %s replies, %s edits, %s clears, %s hostile\n",
	number_format($totals['ops'] ?? 0), number_format(($totals['ops'] ?? 0) / max($elapsed, 0.001)),
	number_format($totals['hits'] ?? 0), number_format($totals['misses'] ?? 0), number_format($totals['puts'] ?? 0), number_format($totals['putsFailed'] ?? 0),
	number_format($totals['replies'] ?? 0), number_format($totals['edits'] ?? 0), number_format($totals['clears'] ?? 0), number_format($totals['hostile'] ?? 0));
printf("hits older than a committed change (the commit-to-forget gap): %s; fragments at rest: %d; superseded stamps left on disk: %d\n\n",
	number_format($totals['staleHits'] ?? 0), $served, $orphans);

$failed = false;
foreach ($checks as $name => $problems) {
	echo ($problems === [] ? '  ok    ' : '  FAIL  ') . $name . ($problems === [] ? '' : ' (' . count($problems) . ')') . "\n";
	foreach (array_slice($problems, 0, 8) as $problem) {
		echo '          ' . $problem . "\n";
	}
	$failed = $failed || $problems !== [];
}

// the scratch tree, truth files and worker reports included
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $entry) {
	$entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
}
@rmdir($scratch);

exit($failed ? 1 : 0);
