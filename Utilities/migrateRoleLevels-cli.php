<?php

declare(strict_types=1);

/**
 * Migrate stored role levels from the old contiguous numbering to the current spaced one.
 *
 *   old: None 0, User 1, Janitor 2, Moderator 3, Admin 4, System 5
 *   new: None 0, User 10, Janitor 25, Moderator 50, Manager 70, Admin 100, System 200
 *
 * Rewrites the `role` column of the account table and the action log table. Safe to re-run: the
 * two numberings only overlap at 0, which means None either way.
 *
 * Logged-in staff keep the old role integer in their session, so their session resolves to None
 * until they log in again. Clearing the PHP session store after migrating avoids the confusion.
 *
 *   php Utilities/migrateRoleLevels-cli.php --dry-run
 *   php Utilities/migrateRoleLevels-cli.php
 */

use Kokonotsuba\account\legacyRoleLevelMap;
use Kokonotsuba\database\databaseConnection;

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$rootDir = dirname(__DIR__);

require $rootDir . '/paths.php';
require $rootDir . '/autoload.php';
require $rootDir . '/bootstrap/libraryIncludes.php';

function printUsage(string $scriptName): void {
	echo "Usage:\n";
	echo "  php {$scriptName} [--dry-run] [--force]\n\n";
	echo "Options:\n";
	echo "  --dry-run   Report what would change without writing anything.\n";
	echo "  --force     Migrate even when unrecognised role values are present (they are left as-is).\n";
}

function parseArguments(array $argv): array {
	$scriptName = $argv[0] ?? 'migrateRoleLevels-cli.php';
	$dryRun = false;
	$force = false;

	foreach (array_slice($argv, 1) as $argument) {
		switch ($argument) {
			case '--dry-run':
				$dryRun = true;
				break;
			case '--force':
				$force = true;
				break;
			case '--help':
			case '-h':
				printUsage($scriptName);
				exit(0);
			default:
				fwrite(STDERR, "Unknown argument: {$argument}\n\n");
				printUsage($scriptName);
				exit(1);
		}
	}

	return ['dryRun' => $dryRun, 'force' => $force];
}

/**
 * Current distribution of the `role` column, as value => row count.
 *
 * @return array<int,int>
 */
function readRoleDistribution(databaseConnection $databaseConnection, string $table): array {
	$rows = $databaseConnection->fetchAllAsArray("SELECT `role`, COUNT(*) AS total FROM {$table} GROUP BY `role`");

	$distribution = [];
	foreach ($rows as $row) {
		$distribution[(int)$row['role']] = (int)$row['total'];
	}

	ksort($distribution);

	return $distribution;
}

function reportTable(string $table, array $distribution, array $classification): int {
	echo "\n{$table}\n";

	if (!$distribution) {
		echo "  (no rows)\n";
		return 0;
	}

	$rowsToMigrate = 0;

	foreach ($distribution as $value => $count) {
		if (in_array($value, $classification['migrate'], true)) {
			$newValue = legacyRoleLevelMap::newValueFor($value);
			echo "  {$count} row(s) with role {$value} -> {$newValue}\n";
			$rowsToMigrate += $count;
		} elseif (in_array($value, $classification['unknown'], true)) {
			echo "  {$count} row(s) with role {$value} - UNRECOGNISED, will be left untouched\n";
		} else {
			echo "  {$count} row(s) with role {$value} - already current, no change\n";
		}
	}

	return $rowsToMigrate;
}

function migrateTable(databaseConnection $databaseConnection, string $table, array $legacyValues): void {
	if (!$legacyValues) {
		return;
	}

	$placeholders = implode(', ', array_fill(0, count($legacyValues), '?'));
	$caseExpression = legacyRoleLevelMap::caseExpression('role');

	$databaseConnection->execute(
		"UPDATE {$table} SET `role` = {$caseExpression} WHERE `role` IN ({$placeholders})",
		array_values($legacyValues)
	);
}

// parsed before connecting so --help works without a database
$arguments = parseArguments($argv);

require $rootDir . '/bootstrap/database.php';

$dbSettings = getDatabaseSettings();

$tables = [
	(string)$dbSettings['ACCOUNT_TABLE'],
	(string)$dbSettings['ACTIONLOG_TABLE'],
];

try {
	$databaseConnection = databaseConnection::getInstance();

	echo "Role level migration (old contiguous numbering -> current userRole values)\n";
	foreach (legacyRoleLevelMap::changingMap() as $legacy => $new) {
		echo "  {$legacy} -> {$new}\n";
	}

	$plan = [];
	$totalRows = 0;
	$unknownFound = false;

	foreach ($tables as $table) {
		$distribution = readRoleDistribution($databaseConnection, $table);
		$classification = legacyRoleLevelMap::classify(array_keys($distribution));

		$totalRows += reportTable($table, $distribution, $classification);
		$unknownFound = $unknownFound || $classification['unknown'] !== [];

		$plan[$table] = $classification['migrate'];
	}

	if ($unknownFound && !$arguments['force']) {
		fwrite(STDERR, "\nUnrecognised role values are present. Check them, then re-run with --force to migrate the rest.\n");
		exit(1);
	}

	if ($totalRows === 0) {
		echo "\nNothing to migrate.\n";
		exit(0);
	}

	if ($arguments['dryRun']) {
		echo "\nDry run enabled. No database changes were made ({$totalRows} row(s) would change).\n";
		exit(0);
	}

	$databaseConnection->beginTransaction();

	try {
		foreach ($plan as $table => $legacyValues) {
			migrateTable($databaseConnection, $table, $legacyValues);
		}

		$databaseConnection->commit();
	} catch (Throwable $throwable) {
		if ($databaseConnection->inTransaction()) {
			$databaseConnection->rollBack();
		}

		throw $throwable;
	}

	echo "\nMigrated {$totalRows} row(s).\n";
	echo "Staff sessions still hold the old role numbers - clear the session store so everyone re-logs in.\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, 'Migration failed: ' . $throwable->getMessage() . "\n");
	exit(1);
}
