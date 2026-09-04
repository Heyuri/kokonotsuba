<?php

/**
 * Integration tests for the staff login brute-force ledger, against a real MariaDB.
 *
 * The unit suite pins the backoff arithmetic. This pins the half that only a live engine can
 * settle: the counting itself. Every window is expressed against the database's NOW() rather
 * than PHP's clock, the counters key off a folded username so case variants cannot each get
 * their own allowance, and a successful login has to stop failures counting toward a lockout
 * while leaving them on record for the warning.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/loginAttempts.php
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
use Kokonotsuba\log_in\loginAttemptPolicy;
use Kokonotsuba\log_in\loginAttemptRepository;
use Kokonotsuba\log_in\loginAttemptService;
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

function assertTrueValue(mixed $condition, string $message): void {
	if ($condition !== true) {
		throw new RuntimeException($message);
	}
}

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

$dsn = getenv('KOKO_TEST_DSN') ?: '';
$user = getenv('KOKO_TEST_USER') ?: '';
$pass = getenv('KOKO_TEST_PASS') ?: '';

if ($dsn === '') {
	fwrite(STDERR, "Set KOKO_TEST_DSN / KOKO_TEST_USER / KOKO_TEST_PASS to a scratch database.\n");
	exit(2);
}

if (!preg_match('/host=([^;]+)/', $dsn, $hostMatch) || !preg_match('/dbname=([^;]+)/', $dsn, $nameMatch)) {
	fwrite(STDERR, "KOKO_TEST_DSN must contain host= and dbname=.\n");
	exit(2);
}

preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);

try {
	$pdo = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
	exit(2);
}

databaseConnection::createInstance([
	'DATABASE_DRIVER' => 'mysql',
	'DATABASE_HOST' => $hostMatch[1],
	'DATABASE_NAME' => $nameMatch[1],
	'DATABASE_CHARSET' => $charsetMatch[1] ?? 'utf8mb4',
	'DATABASE_USERNAME' => $user,
	'DATABASE_PASSWORD' => $pass,
]);

$databaseConnection = databaseConnection::getInstance();
$tableNames = require $root . '/tables.php';

$attemptTable = $tableNames['LOGIN_ATTEMPT_TABLE'];
$accountTable = $tableNames['ACCOUNT_TABLE'];

// ---------------------------------------------------------------------------
// Schema + fixtures
// ---------------------------------------------------------------------------

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

(new migrationRunner(
	$databaseConnection,
	new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']),
	new schemaInspector($databaseConnection, $nameMatch[1]),
	$tableNames,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (string $message, string $level): void {}
))->up();

$pdo->exec("INSERT INTO `{$accountTable}` (username, role, password_hash) VALUES ('Mod', 4, 'x')");
$accountId = (int)$pdo->lastInsertId();

$repository = new loginAttemptRepository($databaseConnection, $attemptTable);

/** Wipe the ledger between cases so each starts from a known state. */
$reset = static function () use ($pdo, $attemptTable): void {
	$pdo->exec("DELETE FROM `{$attemptTable}`");
};

/** Backdate every row, standing in for time passing without sleeping through it. */
$ageAllBy = static function (int $seconds) use ($pdo, $attemptTable): void {
	$pdo->exec("UPDATE `{$attemptTable}` SET attempted_at = attempted_at - INTERVAL {$seconds} SECOND");
};

echo "\nCounting\n";

testCase('failures against one username are counted', function () use ($repository, $reset) {
	$reset();
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');

	$stats = $repository->failureStatsForUsername('Mod', 900);

	assertSameValue(2, $stats['count'], 'failures were not counted');
	assertTrueValue($stats['secondsSinceLast'] !== null, 'no age reported for the newest failure');
	assertTrueValue($stats['secondsSinceLast'] < 5, 'the newest failure was not reported as recent');
});

testCase('case variants share one counter', function () use ($repository, $reset) {
	$reset();
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('MOD', null, '10.0.0.1', 'agent');
	$repository->recordFailure('  mod  ', null, '10.0.0.1', 'agent');

	assertSameValue(3, $repository->failureStatsForUsername('mod', 900)['count'], 'case variants got separate allowances');
});

testCase('a different username has its own counter', function () use ($repository, $reset) {
	$reset();
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');

	assertSameValue(0, $repository->failureStatsForUsername('Janitor', 900)['count'], 'counts leaked across usernames');
});

testCase('failures older than the window stop counting', function () use ($repository, $reset, $ageAllBy) {
	$reset();
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$ageAllBy(1000);

	$stats = $repository->failureStatsForUsername('Mod', 900);

	assertSameValue(0, $stats['count'], 'aged-out failures still counted');
	assertSameValue(null, $stats['secondsSinceLast'], 'an age was reported with nothing in the window');
});

testCase('an IP is counted across every username it tried', function () use ($repository, $reset) {
	$reset();
	$repository->recordFailure('Mod', null, '10.0.0.9', 'agent');
	$repository->recordFailure('Janitor', null, '10.0.0.9', 'agent');
	$repository->recordFailure('Admin', null, '10.0.0.9', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.8', 'agent');

	assertSameValue(3, $repository->failureStatsForIp('10.0.0.9', 900)['count'], 'username spraying was not counted per IP');
	assertSameValue(1, $repository->failureStatsForIp('10.0.0.8', 900)['count'], 'counts leaked across IPs');
});

echo "\nClearing and pruning\n";

testCase('a successful login stops failures counting but keeps them on record', function () use ($repository, $reset, $accountId) {
	$reset();
	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');

	$repository->clearCountedForUsername('Mod');

	assertSameValue(0, $repository->failureStatsForUsername('Mod', 900)['count'], 'cleared failures still counted');
	assertSameValue(2, $repository->pendingWarningForAccount($accountId)['count'], 'cleared failures were lost from the warning');
});

testCase('clearing an IP does not clear another', function () use ($repository, $reset) {
	$reset();
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.2', 'agent');

	$repository->clearCountedForIp('10.0.0.1');

	assertSameValue(0, $repository->failureStatsForIp('10.0.0.1', 900)['count'], 'the cleared IP still counted');
	assertSameValue(1, $repository->failureStatsForIp('10.0.0.2', 900)['count'], 'an unrelated IP was cleared');
});

testCase('pruning drops rows past the retention period only', function () use ($repository, $reset, $accountId, $pdo, $attemptTable) {
	$reset();
	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');
	$pdo->exec("UPDATE `{$attemptTable}` SET attempted_at = attempted_at - INTERVAL 40 DAY");
	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');

	$repository->pruneOlderThan(30);

	assertSameValue(1, $repository->pendingWarningForAccount($accountId)['count'], 'pruning kept the wrong rows');
});

echo "\nWarning\n";

testCase('the warning summarizes what is outstanding', function () use ($repository, $reset, $accountId) {
	$reset();
	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', $accountId, '10.0.0.2', 'agent');
	$repository->recordFailure('Mod', $accountId, '10.0.0.2', 'agent');

	$pending = $repository->pendingWarningForAccount($accountId);

	assertSameValue(3, $pending['count'], 'wrong failure count');
	assertSameValue(2, $pending['addresses'], 'wrong distinct address count');
	assertTrueValue($pending['lastAttempt'] !== null, 'no timestamp for the most recent attempt');
});

testCase('failures against a username with no account raise no warning', function () use ($repository, $reset, $accountId) {
	$reset();
	$repository->recordFailure('nosuchuser', null, '10.0.0.1', 'agent');

	assertSameValue(0, $repository->pendingWarningForAccount($accountId)['count'], 'an unowned failure was attributed to an account');
});

testCase('the warning is shown once per batch', function () use ($repository, $reset, $accountId) {
	$reset();
	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');

	$service = new loginAttemptService($repository, new loginAttemptPolicy(5, 900, 30, 3600), new loginAttemptPolicy(15, 900, 30, 3600));

	assertSameValue(1, $service->takePendingWarning($accountId)['count'], 'the first read did not report the failure');
	assertSameValue(null, $service->takePendingWarning($accountId), 'the warning repeated after being read');

	$repository->recordFailure('Mod', $accountId, '10.0.0.1', 'agent');

	assertSameValue(1, $service->takePendingWarning($accountId)['count'], 'a fresh failure did not raise a new warning');
});

echo "\nThrottle\n";

testCase('the lockout starts at the threshold and unwinds as the window passes', function () use ($repository, $reset, $ageAllBy) {
	$reset();
	$service = new loginAttemptService($repository, new loginAttemptPolicy(3, 900, 30, 3600), new loginAttemptPolicy(15, 900, 30, 3600));

	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	assertSameValue(0, $service->getLockoutSeconds('Mod', '10.0.0.1'), 'locked out below the threshold');

	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	assertTrueValue($service->getLockoutSeconds('Mod', '10.0.0.1') > 0, 'the threshold did not lock the account out');

	$ageAllBy(31);
	assertSameValue(0, $service->getLockoutSeconds('Mod', '10.0.0.1'), 'the lockout outlived its duration');

	$ageAllBy(1000);
	assertSameValue(0, $service->getLockoutSeconds('Mod', '10.0.0.1'), 'aged-out failures still locked the account');
});

testCase('spraying usernames from one IP still locks out', function () use ($repository, $reset) {
	$reset();
	$service = new loginAttemptService($repository, new loginAttemptPolicy(3, 900, 30, 3600), new loginAttemptPolicy(4, 900, 30, 3600));

	foreach (['a', 'b', 'c', 'd'] as $username) {
		$repository->recordFailure($username, null, '10.0.0.7', 'agent');
	}

	assertSameValue(0, $service->getLockoutSeconds('e', '10.0.0.6'), 'an unrelated IP was locked out');
	assertTrueValue($service->getLockoutSeconds('e', '10.0.0.7') > 0, 'the per-IP counter did not lock the sprayer out');
});

testCase('a successful login lifts the lockout', function () use ($repository, $reset) {
	$reset();
	$service = new loginAttemptService($repository, new loginAttemptPolicy(3, 900, 30, 3600), new loginAttemptPolicy(15, 900, 30, 3600));

	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	$repository->recordFailure('Mod', null, '10.0.0.1', 'agent');
	assertTrueValue($service->getLockoutSeconds('Mod', '10.0.0.1') > 0, 'the account was not locked out to begin with');

	$service->recordSuccess('Mod', '10.0.0.1');

	assertSameValue(0, $service->getLockoutSeconds('Mod', '10.0.0.1'), 'the lockout survived a successful login');
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

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
