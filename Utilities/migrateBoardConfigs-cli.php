<?php

/**
 * Migrate pre-database board configuration files into the config editor's database overrides.
 *
 * Before the config editor, settings lived in PHP files:
 *
 *   global/globalBoardConfig.php          - the defaults every board started from
 *   global/board-configs/board-{uid}.php  - a board's own file, requiring the above and
 *                                           reassigning whatever it wanted differently
 *
 * Both are gone from the codebase; an existing install still has them on disk. This reads them and
 * writes the equivalent rows into the board_configs table:
 *
 *   - globalBoardConfig.php  -> the global config row (the reserved GLOBAL board's UID), holding
 *                               everything the old site-wide file changed from today's schema
 *                               defaults.
 *   - board-{uid}.php        -> that board's row, holding only what the board changed from the
 *                               old site-wide file. Anything it merely inherited stays inherited,
 *                               so it keeps following the global config afterwards.
 *
 * Usage:
 *   php Utilities/migrateBoardConfigs-cli.php --legacy=/path/to/old/global [--dry-run] [--verbose]
 *
 *   --legacy=DIR   The old install's global/ directory (holds globalBoardConfig.php and
 *                  board-configs/). Required.
 *   --dry-run      Print what would be written and touch nothing. Do this first.
 *   --verbose      List every converted setting, not just the counts.
 *
 * Safe to re-run: each scope's row is replaced wholesale, never merged into.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__);

require $root . '/autoload.php';
require $root . '/code/Kokonotsuba/constants.php';
require $root . '/paths.php';
require $root . '/bootstrap/libraryIncludes.php';

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configSchema;
use Kokonotsuba\config\legacyConfigConverter;
use Kokonotsuba\database\databaseConnection;

use const Kokonotsuba\GLOBAL_BOARD_UID;

// ---- options ---------------------------------------------------------------

$legacyDir = '';
$dryRun = false;
$verbose = false;

foreach (array_slice($argv, 1) as $arg) {
	if (str_starts_with($arg, '--legacy=')) {
		$legacyDir = rtrim(substr($arg, strlen('--legacy=')), '/') . '/';
	} elseif ($arg === '--dry-run') {
		$dryRun = true;
	} elseif ($arg === '--verbose') {
		$verbose = true;
	} else {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(2);
	}
}

if ($legacyDir === '') {
	fwrite(STDERR, "Usage: php Utilities/migrateBoardConfigs-cli.php --legacy=/path/to/old/global [--dry-run] [--verbose]\n");
	exit(2);
}

$globalBoardConfigFile = $legacyDir . 'globalBoardConfig.php';
if (!is_file($globalBoardConfigFile)) {
	fwrite(STDERR, "No globalBoardConfig.php in {$legacyDir}\n");
	exit(1);
}

/**
 * Load a legacy config file and return the $config array it builds.
 *
 * The old files are a cascade of `require`s ending in assignments to $config, so including one
 * yields the fully-resolved array. They are included in a function scope to keep whatever else
 * they define out of this script.
 */
function loadLegacyConfig(string $file): array {
	$config = [];
	require $file;
	return is_array($config) ? $config : [];
}

// ---- convert ----------------------------------------------------------------

echo "Reading legacy config from {$legacyDir}\n\n";

$legacyGlobal = loadLegacyConfig($globalBoardConfigFile);

// The global row: what the old site-wide file set differently from today's schema defaults.
$schemaDefaults = configSchema::getDefaults();
$globalOverrides = legacyConfigConverter::extractOverrides($legacyGlobal, $schemaDefaults);

// A board's row: what its own file set differently from the old site-wide file. Diffing against
// the legacy global (not the schema defaults) is what keeps a board that simply inherited a value
// following the global config instead of freezing a copy of it.
$legacyGlobalValues = $schemaDefaults;
foreach ($globalOverrides as $dotpath => $value) {
	$legacyGlobalValues[$dotpath] = $value;
}

$boardRows = [];
foreach (glob($legacyDir . 'board-configs/board-*.php') ?: [] as $boardFile) {
	if (!preg_match('/board-(\d+)\.php$/', basename($boardFile), $m)) {
		continue; // board-template.php and friends
	}

	$boardUid = (int)$m[1];
	$legacyBoard = loadLegacyConfig($boardFile);

	$boardRows[$boardUid] = legacyConfigConverter::extractOverrides($legacyBoard, $legacyGlobalValues);
}

// ---- report -----------------------------------------------------------------

$describe = function (array $overrides) use ($verbose): void {
	echo '  ', count($overrides), " override(s)\n";
	if (!$verbose) {
		return;
	}
	foreach ($overrides as $dotpath => $value) {
		echo '    ', $dotpath, ' = ', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
	}
};

echo "Global config (board_uid " . GLOBAL_BOARD_UID . ") from globalBoardConfig.php:\n";
$describe($globalOverrides);

foreach ($boardRows as $boardUid => $overrides) {
	echo "\nBoard {$boardUid}:\n";
	$describe($overrides);
}

if ($dryRun) {
	echo "\nDry run - nothing written.\n";
	exit(0);
}

// ---- write ------------------------------------------------------------------

require $root . '/bootstrap/database.php';

$databaseSettings = getDatabaseSettings();
$configRepository = new configRepository(
	databaseConnection::getInstance(),
	$databaseSettings['BOARD_CONFIG_TABLE']
);

$write = function (int $boardUid, array $overrides) use ($configRepository): void {
	if (empty($overrides)) {
		$configRepository->deleteOverridesForBoardUid($boardUid);
		return;
	}
	$configRepository->saveOverridesForBoardUid($boardUid, $overrides);
};

echo "\nWriting...\n";

$write(GLOBAL_BOARD_UID, $globalOverrides);
echo "  global config written\n";

foreach ($boardRows as $boardUid => $overrides) {
	$write($boardUid, $overrides);
	echo "  board {$boardUid} written\n";
}

echo "\nDone. Rebuild the boards to regenerate their static pages with the migrated config.\n";
