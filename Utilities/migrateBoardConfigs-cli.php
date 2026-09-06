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
 * `php Utilities/migrate-cli.php up` imports them from this install's global/ directory as part
 * of the upgrade, leaving alone any scope that already has overrides. This is the hand-driven
 * version (Kokonotsuba\config\legacyConfigImporter): for files kept elsewhere, a dry run, or
 * replacing what is stored wholesale.
 *
 * Usage:
 *   php Utilities/migrateBoardConfigs-cli.php [--legacy=/path/to/old/global] [--dry-run] [--verbose]
 *
 *   --legacy=DIR   The old install's global/ directory (holds globalBoardConfig.php and
 *                  board-configs/). Defaults to this install's global/.
 *   --dry-run      Print what would be written and touch nothing. Do this first.
 *   --verbose      List every converted setting, not just the counts.
 *
 * Each scope's row is replaced wholesale, never merged into.
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
use Kokonotsuba\config\legacyConfigImporter;
use Kokonotsuba\database\databaseConnection;

use const Kokonotsuba\GLOBAL_BOARD_UID;

// ---- options ---------------------------------------------------------------

$legacyDir = getBackendGlobalDir();
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

// ---- convert ----------------------------------------------------------------

databaseConnection::createInstance(getDatabaseSettings());

$importer = new legacyConfigImporter(
	new configRepository(databaseConnection::getInstance(), getTableName('BOARD_CONFIG_TABLE')),
	$legacyDir,
	legacyConfigImporter::boardConfigFilesFromDatabase(databaseConnection::getInstance(), getTableName('BOARD_TABLE'))
);

if (!$importer->hasSources()) {
	fwrite(STDERR, "No globalBoardConfig.php in {$legacyDir}\n");
	exit(1);
}

echo "Reading legacy config from {$legacyDir}\n\n";

$plan = $importer->load();

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
$describe($plan['global']);

foreach ($plan['boards'] as $boardUid => $overrides) {
	echo "\nBoard {$boardUid}:\n";
	$describe($overrides);
}

if ($dryRun) {
	echo "\nDry run - nothing written.\n";
	exit(0);
}

// ---- write ------------------------------------------------------------------

echo "\nWriting...\n";

$importer->write($plan, false, static function (string $message): void {
	echo $message, "\n";
});

echo "\nDone. Rebuild the boards to regenerate their static pages with the migrated config.\n";
