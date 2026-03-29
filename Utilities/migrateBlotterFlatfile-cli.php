<?php

declare(strict_types=1);

use Kokonotsuba\database\databaseConnection;

$rootDir = dirname(__DIR__);

require $rootDir . '/paths.php';
require $rootDir . '/autoload.php';
require $rootDir . '/bootstrap/libraryIncludes.php';
require $rootDir . '/bootstrap/database.php';

function printUsage(string $scriptName): void {
	echo "Usage:\n";
	echo "  php {$scriptName} [flatfile-path] [--dry-run] [--truncate]\n\n";
	echo "Arguments:\n";
	echo "  flatfile-path   Optional path to the legacy blotter flat file.\n";
	echo "                 Defaults to ModuleSettings.BLOTTER_FILE from global config.\n\n";
	echo "Options:\n";
	echo "  --dry-run       Parse and report entries without writing to the database.\n";
	echo "  --truncate      Delete existing rows from the blotter table before import.\n";
}

function parseArguments(array $argv): array {
	$scriptName = $argv[0] ?? 'migrateBlotterFlatfile-cli.php';
	$flatFilePath = null;
	$dryRun = false;
	$truncate = false;

	foreach (array_slice($argv, 1) as $argument) {
		if ($argument === '--dry-run') {
			$dryRun = true;
			continue;
		}

		if ($argument === '--truncate') {
			$truncate = true;
			continue;
		}

		if ($argument === '--help' || $argument === '-h') {
			printUsage($scriptName);
			exit(0);
		}

		if ($flatFilePath === null) {
			$flatFilePath = $argument;
			continue;
		}

		fwrite(STDERR, "Unknown argument: {$argument}\n\n");
		printUsage($scriptName);
		exit(1);
	}

	return [
		'scriptName' => $scriptName,
		'flatFilePath' => $flatFilePath,
		'dryRun' => $dryRun,
		'truncate' => $truncate,
	];
}

function getDefaultBlotterFilePath(): string {
	$globalConfig = getGlobalConfig();

	if (empty($globalConfig['ModuleSettings']['BLOTTER_FILE'])) {
		throw new RuntimeException('ModuleSettings.BLOTTER_FILE is not configured.');
	}

	return (string) $globalConfig['ModuleSettings']['BLOTTER_FILE'];
}

function normalizeLegacyDate(string $legacyDate): string {
	$legacyDate = trim($legacyDate);

	$acceptedFormats = [
		'Y/m/d H:i:s',
		'Y-m-d H:i:s',
		'Y/m/d',
		'Y-m-d',
	];

	foreach ($acceptedFormats as $format) {
		$dateTime = DateTimeImmutable::createFromFormat($format, $legacyDate);

		if ($dateTime instanceof DateTimeImmutable) {
			if ($format === 'Y/m/d' || $format === 'Y-m-d') {
				$dateTime = $dateTime->setTime(0, 0, 0);
			}

			return $dateTime->format('Y-m-d H:i:s');
		}
	}

	$dateTime = date_create_immutable($legacyDate);

	if ($dateTime instanceof DateTimeImmutable) {
		return $dateTime->format('Y-m-d H:i:s');
	}

	throw new RuntimeException("Unable to parse blotter date: {$legacyDate}");
}

function parseLegacyBlotterEntries(string $flatFilePath): array {
	$lines = file($flatFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	if ($lines === false) {
		throw new RuntimeException("Unable to read blotter file: {$flatFilePath}");
	}

	$entries = [];

	foreach ($lines as $lineNumber => $line) {
		$parts = explode('<>', $line, 3);

		if (count($parts) < 2) {
			throw new RuntimeException('Invalid blotter line format at line ' . ($lineNumber + 1));
		}

		$legacyDate = trim($parts[0]);
		$comment = trim($parts[1]);
		$legacyUid = $parts[2] ?? null;

		$entries[] = [
			'date_added' => normalizeLegacyDate($legacyDate),
			'blotter_content' => $comment,
			'legacy_uid' => $legacyUid !== null ? trim($legacyUid) : null,
		];
	}

	return $entries;
}

function migrateBlotterEntries(
	databaseConnection $databaseConnection,
	string $blotterTable,
	array $entries,
	bool $truncate,
	bool $dryRun
): void {
	if ($dryRun) {
		echo "Dry run enabled. No database changes were made.\n";
		return;
	}

	$databaseConnection->beginTransaction();

	try {
		if ($truncate) {
			$databaseConnection->execute("DELETE FROM {$blotterTable}");
		}

		$query = "
			INSERT INTO {$blotterTable} (blotter_content, added_by, date_added)
			VALUES (:blotter_content, NULL, :date_added)
		";

		foreach ($entries as $entry) {
			$databaseConnection->execute($query, [
				':blotter_content' => $entry['blotter_content'],
				':date_added' => $entry['date_added'],
			]);
		}

		$databaseConnection->commit();
	} catch (Throwable $throwable) {
		if ($databaseConnection->inTransaction()) {
			$databaseConnection->rollBack();
		}

		throw $throwable;
	}
}

$arguments = parseArguments($argv);
$flatFilePath = $arguments['flatFilePath'] ?? getDefaultBlotterFilePath();
$dbSettings = getDatabaseSettings();
$blotterTable = (string) $dbSettings['BLOTTER_TABLE'];

if (!is_file($flatFilePath)) {
	fwrite(STDERR, "Blotter file not found: {$flatFilePath}\n");
	exit(1);
}

try {
	$entries = parseLegacyBlotterEntries($flatFilePath);

	echo 'Source file: ' . $flatFilePath . "\n";
	echo 'Target table: ' . $blotterTable . "\n";
	echo 'Entries parsed: ' . count($entries) . "\n";

	if (!empty($entries)) {
		echo 'Oldest parsed date: ' . $entries[count($entries) - 1]['date_added'] . "\n";
		echo 'Newest parsed date: ' . $entries[0]['date_added'] . "\n";
	}

	migrateBlotterEntries(
		databaseConnection::getInstance(),
		$blotterTable,
		$entries,
		$arguments['truncate'],
		$arguments['dryRun']
	);

	if (!$arguments['dryRun']) {
		echo 'Imported entries: ' . count($entries) . "\n";
	}
} catch (Throwable $throwable) {
	fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . "\n");
	exit(1);
}