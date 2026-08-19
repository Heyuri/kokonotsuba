<?php

/**
 * Fuzz targets for install.php.
 *
 * Included by tests/fuzz.php with $fuzzer in scope.
 *
 * The installer's two pure helpers both sit in front of something that cannot defend itself:
 * sanitizeTableName() is the only guard on identifiers that are string-interpolated straight into
 * DDL, and setNestedInstallConfig() writes an arbitrary dot-path into the array that becomes a
 * board's configuration. Neither may crash, and the first must never let a character through that
 * would end the identifier and start something else.
 *
 * getRootPath()'s regex is fuzzed too: it turns the board's koko.php stub into the path the whole
 * install is then resolved against.
 */

use Koko\Tests\Framework\Fuzzer;
use Koko\Tests\Framework\InstallerHarness;

InstallerHarness::load();

$sanitizeTableName = InstallerHarness::fn('sanitizeTableName');
$setNestedInstallConfig = InstallerHarness::fn('setNestedInstallConfig');
$rootPathPattern = InstallerHarness::rootPathPattern();

/** A name that is plausible-looking as often as it is hostile. */
$tableNameGenerator = static function (): string {
	$safe = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
	$name = '';
	$length = Fuzzer::int(0, 12);
	for ($i = 0; $i < $length; $i++) {
		$name .= $safe[Fuzzer::int(0, strlen($safe) - 1)];
	}

	// Half the time, splice something hostile into an otherwise ordinary name.
	if (Fuzzer::bool()) {
		return $name;
	}
	$injection = Fuzzer::pick([
		'`', "'", '"', ';', ' ', '--', "\n", "\r", "\0", '/*', '*/', '(', ')', '.', '-',
		Fuzzer::nastyString(6),
	]);
	$at = Fuzzer::int(0, strlen($name));
	return substr($name, 0, $at) . $injection . substr($name, $at);
};

// sanitizeTableName: rejection is fine, but anything it *accepts* is about to be interpolated
// into a CREATE TABLE statement unquoted, so it must be an identifier and nothing else.
$fuzzer->target(
	'install\\sanitizeTableName',
	static function (string $name) use ($sanitizeTableName) {
		try {
			return ['accepted' => true, 'value' => $sanitizeTableName($name)];
		} catch (InvalidArgumentException) {
			return ['accepted' => false, 'value' => null];
		}
	},
	fn() => [$tableNameGenerator()],
	[
		['accepted names are returned unchanged', fn($r, $a) => !$r['accepted'] || $r['value'] === $a[0]],
		['accepted names are identifiers and nothing else', fn($r) =>
			!$r['accepted'] || preg_match('/\A[a-zA-Z0-9_]+\z/', $r['value']) === 1],
		['nothing that could break out of the statement is accepted', fn($r) =>
			!$r['accepted'] || strpbrk($r['value'], "`'\";- \t\r\n\0()./*\\") === false],
	]
);

// setNestedInstallConfig: whatever the path, the value must be readable back at it, the array
// must survive as an array, and writing twice must be indistinguishable from writing once.
$fuzzer->target(
	'install\\setNestedInstallConfig',
	static function (array $config, string $dotpath, $value) use ($setNestedInstallConfig) {
		$once = $config;
		$setNestedInstallConfig($once, $dotpath, $value);

		$twice = $once;
		$setNestedInstallConfig($twice, $dotpath, $value);

		return ['once' => $once, 'twice' => $twice];
	},
	function () {
		$segment = fn() => Fuzzer::pick(['modules', 'soudane', 'enabled', 'IMG_DIR', '0', '', 'a.b', Fuzzer::nastyString(6)]);
		$depth = Fuzzer::int(1, 4);
		$path = [];
		for ($i = 0; $i < $depth; $i++) {
			$path[] = $segment();
		}

		$base = Fuzzer::pick([
			[],
			['IMG_DIR' => 'src/'],
			['modules' => ['soudane' => ['enabled' => true]]],
			['modules' => 'off'],
		]);

		$value = Fuzzer::pick([true, false, 0, 42, '', 'src/', ['a' => 1], null, Fuzzer::nastyString(8)]);

		return [$base, implode('.', $path), $value];
	},
	[
		['the config stays an array', fn($r) => is_array($r['once'])],
		['writing twice equals writing once', fn($r) => $r['once'] === $r['twice']],
		['the value is readable back at its path', function ($r, $a) {
			$cursor = $r['once'];
			foreach (explode('.', $a[1]) as $segment) {
				if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
					return false;
				}
				$cursor = $cursor[$segment];
			}
			return $cursor === $a[2];
		}],
	]
);

// getRootPath()'s pattern: whatever it pulls out of the board's koko.php becomes ROOTPATH, so a
// match must be a path that really did come from a require of a koko.php.
$fuzzer->target(
	'install\\rootPathPattern',
	static function (string $line) use ($rootPathPattern) {
		$matches = [];
		return ['matched' => preg_match($rootPathPattern, $line, $matches) === 1, 'path' => $matches[1] ?? null];
	},
	function () {
		$statement = Fuzzer::pick(['require', 'require_once', 'include', '$x =', '// require']);
		$quote = Fuzzer::pick(["'", '"', '']);
		$path = Fuzzer::pick(['/var/www/koko', '/srv/koko', '', '../koko', Fuzzer::nastyString(10)]);
		$file = Fuzzer::pick(['/koko.php', '/index.php', 'koko.php', '/koko.php.bak']);
		return ["<?php $statement $quote$path$file$quote;"];
	},
	[
		['a match always ends at a koko.php', fn($r) => !$r['matched'] || str_ends_with($r['path'], 'koko.php')],
		['the captured path comes from the line', fn($r, $a) => !$r['matched'] || str_contains($a[0], $r['path'])],
		// Note: the pattern is happy with a *commented-out* require, and with mismatched quotes
		// ("path'), because it scans line by line rather than parsing. Neither can be asserted
		// away here - both are recorded in the installer report instead.
		['nothing is matched out of a line with no require', fn($r, $a) =>
			!$r['matched'] || str_contains($a[0], 'require')],
	]
);
