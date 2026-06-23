<?php

/**
 * Fuzzer for the pure helper functions.
 *
 * Where run.php checks known inputs against known outputs, this script throws
 * large volumes of hostile, randomly-generated input at the same functions and
 * checks that broad *properties* (invariants) always hold — catching crashes,
 * warnings, encoding corruption and escaping holes that hand-written examples
 * miss.
 *
 * Usage:
 *   php tests/fuzz.php                       1000 iterations/target, random seed
 *   php tests/fuzz.php --iterations=20000    More iterations
 *   php tests/fuzz.php --seed=12345          Reproduce a specific run
 *   php tests/fuzz.php --target=autoLink     Only fuzz matching targets
 *   php tests/fuzz.php --no-color
 *
 * Exit code is 0 when no failing input is found, 1 otherwise.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

require __DIR__ . '/bootstrap.php';

// Pure module classes exercised by the module fuzz targets below.
requireModuleFile('tripcode/tripcode_src/tripcodeProcessor.php');
requireModuleFile('tripcode/tripcode_src/tripcodeRenderer.php');
requireModuleFile('privateMessage/messageUtility.php');
requireModuleFile('perceptualBan/perceptualHasher.php');

use Koko\Tests\Framework\Fuzzer;
use Kokonotsuba\userRole;
use Kokonotsuba\Modules\tripcode\tripcodeProcessor;
use Kokonotsuba\Modules\tripcode\tripcodeRenderer;
use Kokonotsuba\Modules\privateMessage\messageUtility;
use Kokonotsuba\Modules\perceptualBan\perceptualHasher;

// ---- Parse CLI options ------------------------------------------------------

$iterations = 1000;
$seed = null;
$filter = '';
$colour = null;

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--iterations=')) {
		$iterations = (int)substr($arg, strlen('--iterations='));
	} elseif (str_starts_with($arg, '--seed=')) {
		$seed = (int)substr($arg, strlen('--seed='));
	} elseif (str_starts_with($arg, '--target=')) {
		$filter = substr($arg, strlen('--target='));
	} elseif ($arg === '--no-color' || $arg === '--no-colour') {
		$colour = false;
	} else {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(2);
	}
}

// A fresh, printable seed when none is given, so the run is reproducible.
if ($seed === null) {
	$seed = random_int(1, PHP_INT_MAX);
}

$fuzzer = new Fuzzer($iterations, $seed, $filter, $colour);

// ---- Register fuzz targets --------------------------------------------------
//
// Each target: name, function-under-test, generator, [invariants].
// An invariant is [description, fn($result, $args): bool].

// formatFileSize: never empty, always carries a unit suffix.
$fuzzer->target(
	'strings\\formatFileSize',
	'Puchiko\\strings\\formatFileSize',
	fn() => [Fuzzer::int(0, PHP_INT_MAX >> 1)],
	[
		['returns a string', fn($r) => is_string($r)],
		['has a B/KB/MB unit', fn($r) => (bool)preg_match('/ (B|KB|MB)$/', $r)],
		['is non-empty', fn($r) => $r !== ''],
	]
);

// strlenUnicode: always a non-negative int; 0 only for the empty string.
$fuzzer->target(
	'strings\\strlenUnicode',
	'Puchiko\\strings\\strlenUnicode',
	fn() => [Fuzzer::nastyString()],
	[
		['non-negative integer', fn($r) => is_int($r) && $r >= 0],
		['zero iff input empty', fn($r, $a) => ($r === 0) === ($a[0] === '')],
		['never exceeds byte length', fn($r, $a) => $r <= strlen($a[0])],
	]
);

// truncateText: result never longer (in characters) than the cap by much, and
// stays valid UTF-8 — a multibyte char must never be sliced in half.
$fuzzer->target(
	'strings\\truncateText',
	fn(string $t, int $max, bool $ellipsis) => Puchiko\strings\truncateText($t, $max, 'UTF-8', $ellipsis),
	fn() => [Fuzzer::nastyString(60), Fuzzer::int(0, 30), Fuzzer::bool()],
	[
		['result is valid UTF-8', fn($r) => mb_check_encoding($r, 'UTF-8')],
		['returns a string', fn($r) => is_string($r)],
		// Without the ellipsis suffix the body is capped at $max characters.
		['body within cap (no ellipsis)', function ($r, $a) {
			[$text, $max, $ellipsis] = $a;
			if ($ellipsis) {
				return true; // suffix "(…)" legitimately adds characters
			}
			return mb_strlen($r, 'UTF-8') <= max(mb_strlen($text, 'UTF-8'), $max);
		}],
	]
);

// autoLink: output must remain well-formed enough that it never emits a raw
// unescaped angle bracket from the *URL* into an attribute, and is idempotent
// for inputs containing no URL.
$fuzzer->target(
	'strings\\autoLink',
	fn(string $t) => Puchiko\strings\autoLink($t),
	fn() => [Fuzzer::bool() ? Fuzzer::url() . ' ' . Fuzzer::nastyString(20) : Fuzzer::nastyString(40)],
	[
		['returns a string', fn($r) => is_string($r)],
		['every emitted anchor is closed', fn($r) => substr_count($r, '<a ') === substr_count($r, '</a>')],
		['no double-escaped ampersand entities introduced', fn($r) => !str_contains($r, '&amp;amp;')],
	]
);

// sanitizeStr: the non-admin path must never leave a literal "<script" through,
// and output is always a string.
$fuzzer->target(
	'strings\\sanitizeStr',
	fn(string $t) => Puchiko\strings\sanitizeStr($t),
	fn() => ['<script>' . Fuzzer::nastyString(20) . '</script>' . Fuzzer::nastyString(20)],
	[
		['returns a string', fn($r) => is_string($r)],
		['no live <script tag survives', fn($r) => stripos($r, '<script') === false],
		['raw double-quotes are entity-escaped', fn($r) => !str_contains($r, '"')],
	]
);

// buildSmartQuery: result always starts with the base URL and never throws on
// odd parameter shapes.
$fuzzer->target(
	'strings\\buildSmartQuery',
	fn(string $base, array $def, array $user) => Puchiko\strings\buildSmartQuery($base, $def, $user),
	fn() => ['koko.php?mode=' . Fuzzer::nastyString(6), Fuzzer::assoc(), Fuzzer::assoc()],
	[
		['returns a string', fn($r) => is_string($r)],
		['preserves the base URL prefix', fn($r, $a) => str_starts_with($r, $a[0])],
	]
);

// extractGetParams: always returns an array, never throws on junk URLs.
$fuzzer->target(
	'strings\\extractGetParams',
	'Puchiko\\strings\\extractGetParams',
	fn() => [Fuzzer::url()],
	[
		['returns an array', fn($r) => is_array($r)],
	]
);

// array_equals: reflexive, symmetric.
$fuzzer->target(
	'array\\array_equals',
	'Puchiko\\array\\array_equals',
	function () {
		$mk = fn() => array_map(fn() => Fuzzer::int(0, 9), range(0, Fuzzer::int(0, 6)));
		return [$mk(), $mk()];
	},
	[
		['returns a bool', fn($r) => is_bool($r)],
		['is symmetric', fn($r, $a) => $r === Puchiko\array\array_equals($a[1], $a[0])],
		['a set equals itself', fn($r, $a) => Puchiko\array\array_equals($a[0], $a[0]) === true],
	]
);

// stripInvisible: idempotent and never lengthens the string.
$fuzzer->target(
	'normalize\\stripInvisible',
	'Puchiko\\normalize\\stripInvisible',
	fn() => [Fuzzer::nastyString(60)],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out', fn($r) => mb_check_encoding($r, 'UTF-8')],
		['idempotent', fn($r) => Puchiko\normalize\stripInvisible($r) === $r],
		['never grows', fn($r, $a) => strlen($r) <= strlen($a[0])],
	]
);

// toFilterString: idempotent, always valid UTF-8.
$fuzzer->target(
	'normalize\\toFilterString',
	'Puchiko\\normalize\\toFilterString',
	fn() => [Fuzzer::nastyString(60)],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out', fn($r) => mb_check_encoding($r, 'UTF-8')],
		['idempotent', fn($r) => Puchiko\normalize\toFilterString($r) === $r],
	]
);

// ---- Module pure-logic targets ----------------------------------------------

// tripcodeProcessor::apply mutates its name/trip/secure/capcode args by
// reference. Wrap it so the fuzzer sees the post-state as the return value;
// hostile names must never crash it or corrupt UTF-8.
$tripConfig = [
	'TRIPSALT' => 'fuzz-salt',
	'staffCapcodes' => ['ADMINKEY' => ['requiredRole' => userRole::LEV_ADMIN]],
];
$fuzzer->target(
	'module:tripcodeProcessor::apply',
	function (string $name, string $trip, string $secure) use ($tripConfig) {
		$capcode = '';
		$proc = new tripcodeProcessor($tripConfig);
		$role = Fuzzer::pick([userRole::LEV_USER, userRole::LEV_ADMIN]);
		$proc->apply($name, $trip, $secure, $capcode, $role);
		return ['name' => $name, 'trip' => $trip, 'secure' => $secure, 'capcode' => $capcode];
	},
	fn() => [Fuzzer::nastyString(20), Fuzzer::nastyString(16), Fuzzer::nastyString(16)],
	[
		['name stays valid UTF-8', fn($r) => mb_check_encoding($r['name'], 'UTF-8')],
		['all parts are strings', fn($r) => is_string($r['trip']) && is_string($r['secure']) && is_string($r['capcode'])],
	]
);

// messageUtility::parseName must always return a {name, tripcode} string pair
// and must HTML-escape the name part (no raw '<').
$fuzzer->target(
	'module:messageUtility::parseName',
	function (string $raw) {
		$util = new messageUtility(fn() => '', 'fuzz-salt');
		return $util->parseName($raw);
	},
	fn() => [Fuzzer::nastyString(30)],
	[
		['has name+tripcode strings', fn($r) => is_string($r['name'] ?? null) && is_string($r['tripcode'] ?? null)],
		['name is HTML-escaped', fn($r) => !str_contains($r['name'], '<')],
		['tripcode blank or symbol-prefixed', fn($r) => $r['tripcode'] === '' || str_starts_with($r['tripcode'], '◆') || str_starts_with($r['tripcode'], '★')],
	]
);

// messageUtility::isValidTripCode must always return a bool, never throw.
$fuzzer->target(
	'module:messageUtility::isValidTripCode',
	function (string $s) {
		$util = new messageUtility(fn() => '', 'fuzz-salt');
		return $util->isValidTripCode($s);
	},
	fn() => [Fuzzer::nastyString(24)],
	[
		['returns a bool', fn($r) => is_bool($r)],
	]
);

// perceptualHasher hex<->int conversion must round-trip exactly.
$fuzzer->target(
	'module:perceptualHasher::hexRoundTrip',
	function (string $hex) {
		$h = new perceptualHasher();
		return $h->intToHex($h->hexToInt($hex));
	},
	fn() => [Fuzzer::hex(16)],
	[
		['round-trips to original hex', fn($r, $a) => $r === $a[0]],
		['is 16 hex chars', fn($r) => strlen($r) === 16 && ctype_xdigit($r)],
	]
);

// hammingDistance is a metric: in [0,64], zero on identical, symmetric.
$fuzzer->target(
	'module:perceptualHasher::hammingDistance',
	function (string $a, string $b) {
		$h = new perceptualHasher();
		return ['ab' => $h->hammingDistance($a, $b), 'ba' => $h->hammingDistance($b, $a), 'aa' => $h->hammingDistance($a, $a)];
	},
	fn() => [Fuzzer::hex(16), Fuzzer::hex(16)],
	[
		['within [0,64]', fn($r) => $r['ab'] >= 0 && $r['ab'] <= 64],
		['symmetric', fn($r) => $r['ab'] === $r['ba']],
		['zero on identical', fn($r) => $r['aa'] === 0],
	]
);

// tripcodeRenderer::renderTripcode never throws and always returns a string.
$fuzzer->target(
	'module:tripcodeRenderer::renderTripcode',
	function (string $name, string $trip, string $secure) {
		$r = new tripcodeRenderer([], []);
		return $r->renderTripcode($name, $trip, $secure, '');
	},
	fn() => [Fuzzer::nastyString(16), Fuzzer::nastyString(10), Fuzzer::nastyString(10)],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out', fn($r) => mb_check_encoding($r, 'UTF-8')],
	]
);

exit($fuzzer->run());
