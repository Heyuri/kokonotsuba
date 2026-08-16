<?php

/**
 * Integration tests for the role level migration, run against a real MariaDB.
 *
 * The unit suite pins the shape of the map (tests/unit/Kokonotsuba/LegacyRoleLevelMapTest.php).
 * This runs the UPDATE that migrations/20260815_000002_role_levels.php issues against real tables and
 * checks what the database is left holding - including the parts that only a real engine can
 * settle: that a single CASE statement cannot collide old and new values partway through, and
 * that re-running it is a no-op.
 *
 * The schema below is a cut-down of install.php: the two tables with a `role` column, minus the
 * columns these statements never touch.
 *
 * Usage - point this at a throwaway database; it drops and recreates its tables on every run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/roleLevelMigration.php
 *
 * Exit code is 0 when everything passes, 1 on failure, 2 when no database is reachable.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

require_once __DIR__ . '/../../autoload.php';

use Kokonotsuba\account\legacyRoleLevelMap;
use Kokonotsuba\userRole;

const ACCOUNTS = 'role_migration_accounts';
const ACTIONLOG = 'role_migration_actionlog';

// ---------------------------------------------------------------------------
// Tiny runner
// ---------------------------------------------------------------------------

$passed = 0;
$failed = [];

/** Register and immediately run a case. */
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

try {
	$pdo = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
	fwrite(STDERR, "Set KOKO_TEST_DSN / KOKO_TEST_USER / KOKO_TEST_PASS to a scratch database.\n");
	exit(2);
}

// ---------------------------------------------------------------------------
// Schema + fixtures
// ---------------------------------------------------------------------------

function resetSchema(PDO $pdo): void {
	foreach ([ACCOUNTS, ACTIONLOG] as $table) {
		$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	}

	$pdo->exec('CREATE TABLE `' . ACCOUNTS . '` (
		id INT AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL UNIQUE,
		role INT DEFAULT 0
	) ENGINE=InnoDB');

	$pdo->exec('CREATE TABLE `' . ACTIONLOG . '` (
		id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		name TEXT NOT NULL,
		role INT NOT NULL,
		log_action TEXT NOT NULL,
		INDEX (role)
	) ENGINE=InnoDB');
}

/** Seed one account and one log line per given role value. */
function seed(PDO $pdo, array $roleValues): void {
	$account = $pdo->prepare('INSERT INTO `' . ACCOUNTS . '` (username, role) VALUES (?, ?)');
	$log = $pdo->prepare('INSERT INTO `' . ACTIONLOG . '` (name, role, log_action) VALUES (?, ?, ?)');

	foreach ($roleValues as $index => $roleValue) {
		$account->execute(["user{$index}", $roleValue]);
		$log->execute(["user{$index}", $roleValue, 'did a thing']);
	}
}

/** Run the same UPDATE the CLI issues. Returns the number of rows the engine reports as changed. */
function migrate(PDO $pdo, string $table): int {
	$distinct = $pdo->query("SELECT DISTINCT `role` FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
	$classification = legacyRoleLevelMap::classify(array_map('intval', $distinct));

	if (!$classification['migrate']) {
		return 0;
	}

	$placeholders = implode(', ', array_fill(0, count($classification['migrate']), '?'));
	$caseExpression = legacyRoleLevelMap::caseExpression('role');

	$statement = $pdo->prepare("UPDATE `{$table}` SET `role` = {$caseExpression} WHERE `role` IN ({$placeholders})");
	$statement->execute(array_values($classification['migrate']));

	return $statement->rowCount();
}

/** username => role, for readable assertions. */
function accountRoles(PDO $pdo): array {
	$rows = $pdo->query('SELECT username, role FROM `' . ACCOUNTS . '` ORDER BY id')->fetchAll();

	return array_combine(
		array_column($rows, 'username'),
		array_map('intval', array_column($rows, 'role'))
	);
}

/** role value => row count. */
function roleCounts(PDO $pdo, string $table): array {
	$rows = $pdo->query("SELECT `role`, COUNT(*) AS total FROM `{$table}` GROUP BY `role` ORDER BY `role`")->fetchAll();

	return array_combine(
		array_map('intval', array_column($rows, 'role')),
		array_map('intval', array_column($rows, 'total'))
	);
}

// ---------------------------------------------------------------------------
// Cases
// ---------------------------------------------------------------------------

echo "Role level migration\n";

testCase('every legacy role lands on its current value', function () use ($pdo) {
	resetSchema($pdo);
	seed($pdo, [0, 1, 2, 3, 4, 5]); // None, User, Janitor, Moderator, Admin, System
	migrate($pdo, ACCOUNTS);

	assertSameValue([
		'user0' => userRole::LEV_NONE->value,
		'user1' => userRole::LEV_USER->value,
		'user2' => userRole::LEV_JANITOR->value,
		'user3' => userRole::LEV_MODERATOR->value,
		'user4' => userRole::LEV_ADMIN->value,
		'user5' => userRole::LEV_SYSTEM->value,
	], accountRoles($pdo), 'accounts should hold the current role values');
});

testCase('the action log is migrated the same way', function () use ($pdo) {
	resetSchema($pdo);
	seed($pdo, [1, 2, 3, 4, 4, 4]);
	migrate($pdo, ACTIONLOG);

	assertSameValue([
		userRole::LEV_USER->value => 1,
		userRole::LEV_JANITOR->value => 1,
		userRole::LEV_MODERATOR->value => 1,
		userRole::LEV_ADMIN->value => 3,
	], roleCounts($pdo, ACTIONLOG), 'log rows should be remapped and counts preserved');
});

testCase('a single CASE cannot collide old and new values', function () use ($pdo) {
	// The hazard a naive row-by-row remap has: promote 1 -> 10 first and a later pass over the
	// same table would see 10 and try to move it again. One CASE statement evaluates every row
	// against the original values, so nothing double-hops.
	resetSchema($pdo);
	seed($pdo, [1, 2, 3, 4]);
	migrate($pdo, ACCOUNTS);

	$counts = roleCounts($pdo, ACCOUNTS);
	assertSameValue(4, array_sum($counts), 'no rows should be lost');
	assertSameValue(4, count($counts), 'each role should stay distinct');
	assertSameValue(false, array_key_exists(1, $counts), 'no legacy value should survive');
});

testCase('re-running the migration changes nothing', function () use ($pdo) {
	resetSchema($pdo);
	seed($pdo, [0, 1, 2, 3, 4, 5]);
	migrate($pdo, ACCOUNTS);
	$afterFirstRun = accountRoles($pdo);

	$changed = migrate($pdo, ACCOUNTS);

	assertSameValue(0, $changed, 'the second run should touch no rows');
	assertSameValue($afterFirstRun, accountRoles($pdo), 'roles should be unchanged by the second run');
});

testCase('an already-migrated table is left alone', function () use ($pdo) {
	resetSchema($pdo);
	seed($pdo, [
		userRole::LEV_USER->value,
		userRole::LEV_MANAGER->value,
		userRole::LEV_ADMIN->value,
	]);

	assertSameValue(0, migrate($pdo, ACCOUNTS), 'nothing should need migrating');
	assertSameValue([
		'user0' => userRole::LEV_USER->value,
		'user1' => userRole::LEV_MANAGER->value,
		'user2' => userRole::LEV_ADMIN->value,
	], accountRoles($pdo), 'current values should be untouched');
});

testCase('unrecognised values are left untouched', function () use ($pdo) {
	// The CLI refuses to run without --force when it sees these; if it is forced, they survive.
	resetSchema($pdo);
	seed($pdo, [3, 42, -7]);
	migrate($pdo, ACCOUNTS);

	assertSameValue([
		'user0' => userRole::LEV_MODERATOR->value,
		'user1' => 42,
		'user2' => -7,
	], accountRoles($pdo), 'only the legacy value should have moved');
});

testCase('no legacy row ends up as Manager', function () use ($pdo) {
	// Manager is new; nothing in an old database can have meant it.
	resetSchema($pdo);
	seed($pdo, [0, 1, 2, 3, 4, 5]);
	migrate($pdo, ACCOUNTS);

	$counts = roleCounts($pdo, ACCOUNTS);
	assertSameValue(false, array_key_exists(userRole::LEV_MANAGER->value, $counts), 'Manager should be empty after migrating');
});

testCase('an empty table is handled', function () use ($pdo) {
	resetSchema($pdo);

	assertSameValue(0, migrate($pdo, ACCOUNTS), 'an empty table should need no work');
});

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

foreach ([ACCOUNTS, ACTIONLOG] as $table) {
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}

echo "\n";

if ($failed) {
	echo "\033[31m" . count($failed) . " failed\033[0m, {$passed} passed\n\n";
	foreach ($failed as $failure) {
		echo "  - {$failure}\n";
	}
	exit(1);
}

echo "\033[32mAll {$passed} passed\033[0m\n";
exit(0);
