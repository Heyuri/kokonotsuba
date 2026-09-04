<?php

declare(strict_types=1);

/**
 * Import the legacy blotter flat file into the blotter table.
 *
 * `php Utilities/migrate-cli.php up` imports global/blotter.txt as part of the upgrade; this is
 * the hand-driven version (Kokonotsuba\Modules\blotter\legacyBlotterImporter), for a file kept
 * somewhere else, a dry run, or replacing the table's contents outright.
 *
 *   php Utilities/migrateBlotterFlatfile-cli.php [flatfile-path] [--dry-run] [--truncate]
 *
 *   flatfile-path   Path to the legacy blotter flat file. Defaults to global/blotter.txt.
 *   --dry-run       Parse and report entries without writing to the database.
 *   --truncate      Delete existing rows from the blotter table before import.
 *
 * Without --truncate an entry whose text and date are already in the table is skipped.
 */

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\Modules\blotter\legacyBlotterImporter;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir . '/paths.php';
require $rootDir . '/autoload.php';
require $rootDir . '/code/Kokonotsuba/constants.php';
require $rootDir . '/module/blotter/legacyBlotterImporter.php';

function printUsage(string $scriptName): void {
	echo "Usage:\n";
	echo "  php {$scriptName} [flatfile-path] [--dry-run] [--truncate]\n";
}

$scriptName = $argv[0] ?? 'migrateBlotterFlatfile-cli.php';
$flatFilePath = null;
$dryRun = false;
$truncate = false;

foreach (array_slice($argv, 1) as $argument) {
	if ($argument === '--dry-run') {
		$dryRun = true;
	} elseif ($argument === '--truncate') {
		$truncate = true;
	} elseif ($argument === '--help' || $argument === '-h') {
		printUsage($scriptName);
		exit(0);
	} elseif ($flatFilePath === null && !str_starts_with($argument, '--')) {
		$flatFilePath = $argument;
	} else {
		fwrite(STDERR, "Unknown argument: {$argument}\n\n");
		printUsage($scriptName);
		exit(1);
	}
}

$flatFilePath ??= getBackendGlobalDir() . 'blotter.txt';

if (!is_file($flatFilePath)) {
	fwrite(STDERR, "Blotter file not found: {$flatFilePath}\n");
	exit(1);
}

databaseConnection::createInstance(getDatabaseSettings());
$databaseConnection = databaseConnection::getInstance();
$blotterTable = getTableName('BLOTTER_TABLE');

try {
	$entries = legacyBlotterImporter::parseFile($flatFilePath);

	echo 'Source file: ' . $flatFilePath . "\n";
	echo 'Target table: ' . $blotterTable . "\n";
	echo 'Entries parsed: ' . count($entries) . "\n";

	if ($entries !== []) {
		echo 'Oldest parsed date: ' . $entries[count($entries) - 1]['date_added'] . "\n";
		echo 'Newest parsed date: ' . $entries[0]['date_added'] . "\n";
	}

	$importer = new legacyBlotterImporter($databaseConnection, $blotterTable);

	if ($dryRun) {
		$result = $importer->import($entries, true, $truncate);
		echo "Dry run: {$result['imported']} would be imported, {$result['skipped']} already present. No database changes were made.\n";
		exit(0);
	}

	$databaseConnection->beginTransaction();

	try {
		$result = $importer->import($entries, false, $truncate);
		$databaseConnection->commit();
	} catch (Throwable $throwable) {
		if ($databaseConnection->inTransaction()) {
			$databaseConnection->rollBack();
		}

		throw $throwable;
	}

	echo "Imported entries: {$result['imported']}, already present: {$result['skipped']}\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . "\n");
	exit(1);
}
