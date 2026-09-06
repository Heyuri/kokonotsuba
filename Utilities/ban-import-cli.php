<?php

declare(strict_types=1);

/**
 * Import the old flat-file bans into the bans table.
 *
 * Bans used to be CSV lines (`ip,start,expires,reason`) in each board's bans.log.txt and in
 * global/globalbans.log. `php Utilities/migrate-cli.php up` imports the live ones as part of the
 * upgrade; this is the hand-driven version (Kokonotsuba\ban\legacyBanImporter), for a dry run or
 * for bringing the expired entries across too. The files are left on disk untouched.
 *
 *   php Utilities/ban-import-cli.php [--dry-run] [--include-expired] [--no-color]
 *
 * Imported rows get the default checkpoints (posting, reports, banners), which is what a file ban
 * meant. A line whose start equals its expiry was a warning and comes across as one.
 *
 * Safe to run twice: a ban already in the table with the same pattern, board and filing time is
 * skipped rather than duplicated.
 */

use Kokonotsuba\ban\legacyBanImporter;
use Kokonotsuba\database\databaseConnection;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir.'/paths.php';
require $rootDir.'/autoload.php';
require $rootDir.'/code/Kokonotsuba/constants.php';

$hasFlag = static function (string $name) use ($argv): bool {
	return in_array("--{$name}", array_slice($argv, 1), true);
};

$dryRun = $hasFlag('dry-run');
$includeExpired = $hasFlag('include-expired');
$useColor = !$hasFlag('no-color') && getenv('NO_COLOR') === false;

$paint = static function (string $text, string $color) use ($useColor): string {
	if (!$useColor) {
		return $text;
	}

	$codes = ['red' => '0;31', 'green' => '0;32', 'yellow' => '0;33', 'dim' => '0;90'];

	return "\033[{$codes[$color]}m{$text}\033[0m";
};

databaseConnection::createInstance(getDatabaseSettings());

$importer = new legacyBanImporter(
	databaseConnection::getInstance(),
	getTableName('BAN_TABLE'),
	getTableName('BOARD_TABLE'),
	getBackendGlobalDir(),
	getBoardStoragesDir()
);

if (!$importer->hasSources()) {
	echo "No legacy ban files found.\n";
	exit(0);
}

$result = $importer->import($dryRun, $includeExpired, static function (string $message) use ($paint): void {
	echo (str_starts_with($message, '  ') ? $message : $paint($message, 'dim'))."\n";
});

echo $paint("imported {$result['imported']}", 'green')
	.', '.$paint("already present {$result['skipped']}", 'yellow')
	.', '.$paint("expired and left behind {$result['expired']}", 'dim')."\n";

if ($result['expired'] > 0 && !$includeExpired) {
	echo "Pass --include-expired to bring the expired ones across too.\n";
}
