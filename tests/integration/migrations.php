<?php

/**
 * Integration tests for the migration runner, against a real MariaDB.
 *
 * The unit suite covers the parts that need no database (filename parsing, SQL emission,
 * placeholder expansion). This covers what only a live engine can settle: that the squashed
 * baseline really does build the schema, that reconciliation repairs an aged database without
 * touching its data, that re-running is a no-op, and that an irreversible migration refuses to
 * roll back.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/migrations.php
 *
 * Exit code is 0 when everything passes, 1 on failure, 2 when no database is reachable.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__, 2);

require_once $root . '/autoload.php';
require_once $root . '/code/Kokonotsuba/constants.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\irreversibleMigrationException;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;

// ---------------------------------------------------------------------------
// Tiny runner
// ---------------------------------------------------------------------------

$passed = 0;
$failed = [];

function testCase(string $name, callable $fn): void {
	global $passed, $failed;

	try {
		$fn();
		$passed++;
		echo "  \033[32m✓\033[0m {$name}\n";
	} catch (Throwable $e) {
		echo "  \033[31m✗\033[0m {$name}\n";
		$failed[] = $name . "\n      " . str_replace("\n", "\n      ", $e->getMessage());
	}
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . "\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true)
		);
	}
}

function assertTrueValue(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

$dsn = getenv('KOKO_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4';
$user = getenv('KOKO_TEST_USER') ?: 'koko_test';
$pass = getenv('KOKO_TEST_PASS') ?: 'kokotest';

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
	fwrite(STDERR, "pdo_mysql is not installed; cannot run the integration suite.\n");
	exit(2);
}

preg_match('/host=([^;]+)/', $dsn, $hostMatch);
preg_match('/dbname=([^;]+)/', $dsn, $nameMatch);
preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);

if (!isset($hostMatch[1], $nameMatch[1])) {
	fwrite(STDERR, "KOKO_TEST_DSN must carry host= and dbname=.\n");
	exit(2);
}

$databaseName = $nameMatch[1];

try {
	$pdo = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
	fwrite(STDERR, "Set KOKO_TEST_DSN / KOKO_TEST_USER / KOKO_TEST_PASS to a scratch database.\n");
	exit(2);
}

databaseConnection::createInstance([
	'DATABASE_DRIVER' => 'mysql',
	'DATABASE_HOST' => $hostMatch[1],
	'DATABASE_NAME' => $databaseName,
	'DATABASE_CHARSET' => $charsetMatch[1] ?? 'utf8mb4',
	'DATABASE_USERNAME' => $user,
	'DATABASE_PASSWORD' => $pass,
]);

$databaseConnection = databaseConnection::getInstance();
$tableNames = require $root . '/tables.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function dropEverything(PDO $pdo): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
		$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	}
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function liveTables(PDO $pdo): array {
	$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
	sort($tables);

	return $tables;
}

$makeRunner = static function () use ($databaseConnection, $tableNames, $databaseName, $root): migrationRunner {
	return new migrationRunner(
		$databaseConnection,
		new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']),
		new schemaInspector($databaseConnection, $databaseName),
		$tableNames,
		$root,
		Kokonotsuba\KOKO_VERSION,
		static function (string $message, string $level): void {}
	);
};

$expectedTables = array_values(array_diff(array_values($tableNames), ['schema_migrations']));
sort($expectedTables);

echo "Migration runner integration tests ({$databaseName})\n\n";

// ---------------------------------------------------------------------------
// Fresh install
// ---------------------------------------------------------------------------

echo "Fresh install\n";

testCase('up on an empty database applies every migration', function () use ($pdo, $makeRunner) {
	dropEverything($pdo);

	$runner = $makeRunner();
	$applied = $runner->up();

	assertTrueValue(count($applied) >= 2, 'expected at least the baseline and one follow-up');
});

testCase('every table in tables.php exists afterwards', function () use ($pdo, $tableNames, $expectedTables) {
	$live = liveTables($pdo);

	foreach ($expectedTables as $table) {
		assertTrueValue(in_array($table, $live, true), "missing table after up(): {$table}");
	}

	assertTrueValue(in_array($tableNames['SCHEMA_MIGRATION_TABLE'], $live, true), 'ledger table was not created');
});

testCase('running up again changes nothing', function () use ($makeRunner) {
	assertSameValue([], $makeRunner()->up(), 'a second up() applied something');
});

testCase('nothing is pending', function () use ($makeRunner) {
	assertSameValue([], $makeRunner()->pending(), 'migrations still pending after up()');
});

testCase('doctor reports no drift against a freshly migrated database', function () use ($makeRunner) {
	assertSameValue([], $makeRunner()->doctor(), 'doctor found drift on a fresh install');
});

// ---------------------------------------------------------------------------
// Reconciling a database that predates the ledger
// ---------------------------------------------------------------------------

echo "\nInstall predating the ledger\n";

testCase('baseline recreates missing tables, columns and indexes without losing data', function () use ($pdo, $tableNames, $makeRunner, $expectedTables) {
	// Age the schema: drop the ledger (so it looks pre-framework), a whole table, a column and
	// an index — then leave a row behind to prove reconciliation is not a rebuild.
	$pdo->exec("DROP TABLE `{$tableNames['SCHEMA_MIGRATION_TABLE']}`");
	$pdo->exec("DROP TABLE `{$tableNames['POST_NUMBER_HISTORY_TABLE']}`");
	$pdo->exec("ALTER TABLE `{$tableNames['POST_TABLE']}` DROP COLUMN `tag`");
	$pdo->exec("ALTER TABLE `{$tableNames['POST_TABLE']}` DROP INDEX `idx_host`");
	$pdo->exec(
		"INSERT INTO `{$tableNames['BOARD_TABLE']}`
		 (board_uid, board_identifier, board_title, board_sub_title, storage_directory_name)
		 VALUES (1, 'b', 'Random', '', 'b')"
	);

	$result = $makeRunner()->baseline();

	assertTrueValue($result['reconciled'] !== [], 'baseline reported no differences on an aged database');

	$live = liveTables($pdo);
	foreach ($expectedTables as $table) {
		assertTrueValue(in_array($table, $live, true), "still missing after baseline: {$table}");
	}

	$columns = $pdo->query("SHOW COLUMNS FROM `{$tableNames['POST_TABLE']}` LIKE 'tag'")->fetchAll();
	assertSameValue(1, count($columns), 'dropped column was not restored');

	$indexes = $pdo->query("SHOW INDEX FROM `{$tableNames['POST_TABLE']}` WHERE Key_name = 'idx_host'")->fetchAll();
	assertTrueValue($indexes !== [], 'dropped index was not restored');

	$boards = (int)$pdo->query("SELECT COUNT(*) FROM `{$tableNames['BOARD_TABLE']}`")->fetchColumn();
	assertSameValue(1, $boards, 'reconciliation destroyed existing rows');
});

testCase('doctor is clean again after baseline', function () use ($makeRunner) {
	assertSameValue([], $makeRunner()->doctor(), 'drift remained after baseline');
});

testCase('baseline leaves an already-current database alone', function () use ($makeRunner) {
	$result = $makeRunner()->baseline();

	assertSameValue([], $result['reconciled'], 'baseline changed a database that was already current');
	assertSameValue([], $result['stamped'], 'baseline re-stamped migrations it had already recorded');
});

// ---------------------------------------------------------------------------
// Detection
// ---------------------------------------------------------------------------

echo "\nDetection and stamping\n";

testCase('a data migration whose effect is already present is stamped, not re-run', function () use ($pdo, $tableNames, $makeRunner) {
	$ledgerTable = $tableNames['SCHEMA_MIGRATION_TABLE'];
	$pdo->exec("DELETE FROM `{$ledgerTable}` WHERE `name` = 'role_levels'");

	// No legacy role values present, so detect() should return true and baseline should stamp it.
	$result = $makeRunner()->baseline();
	$stampedNames = array_map(static fn ($migration): string => $migration->name, $result['stamped']);

	assertTrueValue(in_array('role_levels', $stampedNames, true), 'role_levels was not stamped by detection');
});

testCase('a data migration with work left to do is not stamped', function () use ($pdo, $tableNames, $makeRunner) {
	$pdo->exec("DELETE FROM `{$tableNames['SCHEMA_MIGRATION_TABLE']}` WHERE `name` = 'role_levels'");
	$pdo->exec(
		"INSERT INTO `{$tableNames['ACCOUNT_TABLE']}` (username, password_hash, role) VALUES ('legacy', 'x', 4)"
	);

	$result = $makeRunner()->baseline();
	$stampedNames = array_map(static fn ($migration): string => $migration->name, $result['stamped']);
	$pendingNames = array_map(static fn ($migration): string => $migration->name, $result['pending']);

	assertTrueValue(!in_array('role_levels', $stampedNames, true), 'role_levels was stamped despite legacy data');
	assertTrueValue(in_array('role_levels', $pendingNames, true), 'role_levels was not left pending');
});

testCase('up then rewrites the legacy values', function () use ($pdo, $tableNames, $makeRunner) {
	$makeRunner()->up();

	$role = (int)$pdo->query(
		"SELECT role FROM `{$tableNames['ACCOUNT_TABLE']}` WHERE username = 'legacy'"
	)->fetchColumn();

	assertSameValue(Kokonotsuba\userRole::LEV_ADMIN->value, $role, 'legacy role was not migrated');
});

// ---------------------------------------------------------------------------
// Rollback
// ---------------------------------------------------------------------------

echo "\nRollback\n";

testCase('an irreversible migration refuses to roll back', function () use ($makeRunner) {
	$threw = false;

	try {
		$makeRunner()->down(1);
	} catch (irreversibleMigrationException) {
		$threw = true;
	}

	assertTrueValue($threw, 'down() did not refuse an irreversible migration');
});

testCase('down on the baseline drops every table it created', function () use ($pdo, $tableNames, $makeRunner) {
	// Forget the irreversible migration so the baseline becomes the newest applied one.
	$pdo->exec("DELETE FROM `{$tableNames['SCHEMA_MIGRATION_TABLE']}` WHERE `name` = 'role_levels'");

	$makeRunner()->down(1);

	$live = liveTables($pdo);
	$remaining = array_values(array_diff($live, [$tableNames['SCHEMA_MIGRATION_TABLE']]));

	assertSameValue([], $remaining, 'tables survived the baseline rollback');
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

dropEverything($pdo);

echo "\n";

if ($failed !== []) {
	echo "\033[31mFailures:\033[0m\n";
	foreach ($failed as $failure) {
		echo "  - {$failure}\n";
	}
	echo "\n{$passed} passed, " . count($failed) . " failed\n";
	exit(1);
}

echo "\033[32m{$passed} passed\033[0m\n";
exit(0);
