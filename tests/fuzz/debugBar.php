<?php

/**
 * Fuzz targets for the debug bar and the request profiler.
 *
 * Included by tests/fuzz.php with $fuzzer in scope.
 *
 * Three things eat input here that nobody vets: the request URI the profile link is built from,
 * the SQL text and timings the profile lists, and the counters the bar formats. None of them
 * may crash, warn, corrupt encoding, or hand markup through to the page.
 */

use Koko\Tests\Framework\Fuzzer;
use Kokonotsuba\debug\requestMetrics;
use Kokonotsuba\debug\requestProfiler;
use Kokonotsuba\Modules\debugBar\debugBarFormatter;

requireModuleFile('debugBar/debugBarFormatter.php');

/** A request URI as a hostile client might send it. */
$fuzzRequestUri = static function (): string {
	$path = Fuzzer::pick(['/b/koko.php', '/koko.php', '/', '', '/a/b/c.php', '/' . rawurlencode(Fuzzer::nastyString(12)), Fuzzer::nastyString(20)]);
	$pairs = [];
	for ($i = Fuzzer::int(0, 5); $i > 0; $i--) {
		$key = Fuzzer::pick(['res', 'page', 'mode', requestProfiler::PARAMETER, 'a[]', 'a[b]', '', Fuzzer::nastyString(8), rawurlencode(Fuzzer::nastyString(8))]);
		$value = Fuzzer::pick(['1', '', '0', Fuzzer::nastyString(12), rawurlencode(Fuzzer::nastyString(12)), '%', '%zz', '+', '&', '=']);
		$pairs[] = Fuzzer::bool() ? $key . '=' . $value : $key;
	}
	$query = implode(Fuzzer::pick(['&', '&&', ';']), $pairs);
	$suffix = Fuzzer::pick(['', '?', '?' . $query, '?' . $query . '#frag', '??' . $query]);

	return $path . $suffix;
};

// profileUrlFor: one profile parameter, the path untouched, and nothing a template could
// mistake for markup once it is escaped.
$fuzzer->target(
	'debugBar:requestProfiler::profileUrlFor',
	[requestProfiler::class, 'profileUrlFor'],
	fn() => [$fuzzRequestUri()],
	[
		['returns a string', fn($r) => is_string($r)],
		['carries the parameter exactly once', fn($r) => preg_match_all('/(?:\?|&)' . requestProfiler::PARAMETER . '=1(?:&|$)/', $r) === 1],
		['keeps the path', fn($r, $a) => str_starts_with($r, explode('?', $a[0], 2)[0] . '?')],
		['query is URL-encoded (no raw < > " or space)', fn($r) => !preg_match('/[<>" ]/', explode('?', $r, 2)[1])],
		['no control bytes survive', fn($r) => !preg_match('/[\x00-\x1F\x7F]/', explode('?', $r, 2)[1])],
	]
);

/** A counters snapshot, including values no honest clock would produce. */
$fuzzSnapshot = static function (): array {
	$number = static fn() => Fuzzer::pick([
		0.0, -0.0, 1e-9, 0.5, 12.25, 1e6, -1.0, (float)PHP_INT_MAX, INF, -INF, NAN, 3, '2.5', 'abc', null,
	]);

	return [
		'wallSeconds' => $number(),
		'cpuSeconds' => $number(),
		'cpuUserSeconds' => $number(),
		'cpuSystemSeconds' => $number(),
		'queryCount' => Fuzzer::pick([0, 1, 12, PHP_INT_MAX, -5, '7', null]),
		'querySeconds' => $number(),
		'peakMemoryBytes' => Fuzzer::pick([0, 1, 1048576, PHP_INT_MAX, -1, '4194304', null]),
	];
};

// debugBarFormatter: always four strings with units, and never markup.
$fuzzer->target(
	'debugBar:debugBarFormatter::format',
	[debugBarFormatter::class, 'format'],
	fn() => [$fuzzSnapshot()],
	[
		['four string fields', fn($r) => is_array($r) && count($r) === 4 && count(array_filter($r, 'is_string')) === 4],
		['total and queries carry ms', fn($r) => str_ends_with($r['total'], ' ms') && str_ends_with($r['queries'], ' ms')],
		['cpu carries a percentage', fn($r) => (bool)preg_match('/ ms \(-?[\d,]+%\)$/', $r['cpu'])],
		['memory carries MB', fn($r) => str_ends_with($r['memory'], ' MB')],
		['no markup', fn($r) => !preg_match('/[<>&"]/', implode('', $r))],
	]
);

/** Statement rows as the connection records them, plus shapes it never would. */
$fuzzStatements = static function (): array {
	$rows = [];
	for ($i = Fuzzer::int(0, 6); $i > 0; $i--) {
		$rows[] = Fuzzer::pick([
			['sql' => 'SELECT * FROM posts WHERE post_uid = ?', 'seconds' => 0.0012],
			['sql' => Fuzzer::nastyString(80), 'seconds' => Fuzzer::pick([0.0, 1e-9, 2.5, -1.0, INF, NAN, '0.1', null])],
			['sql' => str_repeat('x', 5000), 'seconds' => 0.1],
			['sql' => null, 'seconds' => 0.1],
			['seconds' => 0.2],
			[],
		]);
	}

	return $rows;
};

// buildDocument: what speedscope needs is preserved, the extra block is well-formed, and the
// whole thing survives json_encode - a profile that cannot be written is no profile.
$fuzzer->target(
	'debugBar:requestProfiler::buildDocument',
	[requestProfiler::class, 'buildDocument'],
	fn() => [
		Fuzzer::pick([[], ['$schema' => 's', 'profiles' => [], 'shared' => ['frames' => []]], ['name' => 'old', 'koko' => 'stale']]),
		$fuzzSnapshot(),
		$fuzzStatements(),
		$fuzzRequestUri(),
	],
	[
		['speedscope keys are kept', fn($r, $a) => array_diff_key($a[0], ['name' => 1, 'koko' => 1]) == array_diff_key($r, ['name' => 1, 'koko' => 1])],
		['has a non-empty name', fn($r) => is_string($r['name']) && $r['name'] !== ''],
		['query count is an int', fn($r) => is_int($r['koko']['queries']['count'])],
		['one statement row per input row', fn($r, $a) => count($r['koko']['queries']['statements']) === count($a[2])],
		['statement rows are sql + ms', fn($r) => array_reduce($r['koko']['queries']['statements'], fn($ok, $s) => $ok && is_string($s['sql']) && is_float($s['ms']), true)],
		['encodes as JSON', fn($r) => json_encode($r, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) !== false],
	]
);

// compactSql: one line, bounded, valid UTF-8 for anything the connection could be handed.
$fuzzer->target(
	'debugBar:requestMetrics::compactSql',
	[requestMetrics::class, 'compactSql'],
	fn() => [Fuzzer::pick([Fuzzer::nastyString(200), str_repeat(Fuzzer::nastyString(4), 1000), "SELECT\n\t1\r\n  FROM  x", ''])],
	[
		['single line', fn($r) => !preg_match('/[\r\n\t]/', $r)],
		['bounded', fn($r) => strlen($r) <= requestMetrics::STATEMENT_TEXT_LIMIT + 3],
		['no leading or trailing space', fn($r) => $r === trim($r)],
	]
);

// recordQuery: the count is exact, the sum is the sum, and the kept statements never exceed the cap.
$fuzzer->target(
	'debugBar:requestMetrics::recordQuery',
	function (array $durations, bool $keep) {
		requestMetrics::begin(0.0);
		requestMetrics::keepStatements($keep);
		foreach ($durations as $seconds) {
			requestMetrics::recordQuery($seconds, 'SELECT ' . $seconds);
		}
		$result = ['snapshot' => requestMetrics::snapshot(1.0), 'statements' => requestMetrics::statements()];
		requestMetrics::keepStatements(false);

		return $result;
	},
	fn() => [array_map(fn() => Fuzzer::int(0, 100000) / 1e6, range(1, Fuzzer::int(0, 40))), Fuzzer::bool()],
	[
		['count matches', fn($r, $a) => $r['snapshot']['queryCount'] === count($a[0])],
		['sum matches', fn($r, $a) => abs($r['snapshot']['querySeconds'] - array_sum($a[0])) < 1e-9],
		['statements kept only when asked', fn($r, $a) => count($r['statements']) === ($a[1] ? count($a[0]) : 0)],
		['wall time is one second', fn($r) => abs($r['snapshot']['wallSeconds'] - 1.0) < 1e-9],
	]
);
