<?php

/**
 * Integration tests for IP anonymization, against a real MariaDB.
 *
 * The unit suite covers the service's orchestration with a stub repository. This covers what
 * only a live engine can settle: that every target's SQL actually parses and matches, that the
 * PHP-side hash agrees with the one MariaDB computes, that a ban still enforceable is left
 * alone, and that a second run is a no-op.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/anonIp.php
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
require_once $root . '/module/anonIp/anonIpTarget.php';
require_once $root . '/module/anonIp/anonIpTargets.php';
require_once $root . '/module/anonIp/anonIpRepository.php';
require_once $root . '/module/anonIp/anonIpRunRepository.php';
require_once $root . '/module/anonIp/anonIpScheduler.php';
require_once $root . '/module/anonIp/anonIpService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\ip\ipAnonymizer;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\Modules\anonIp\anonIpRepository;
use Kokonotsuba\Modules\anonIp\anonIpRunRepository;
use Kokonotsuba\Modules\anonIp\anonIpService;
use Kokonotsuba\Modules\anonIp\anonIpTarget;
use Kokonotsuba\Modules\anonIp\anonIpTargets;

const ANON_TEST_SALT = 'integration-test-salt-not-a-real-secret';

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
// Schema and fixtures
// ---------------------------------------------------------------------------

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

(new migrationRunner(
	$databaseConnection,
	new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']),
	new schemaInspector($databaseConnection, $databaseName),
	$tableNames,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (string $message, string $level): void {}
))->up();

$anonymizer = new ipAnonymizer(ANON_TEST_SALT);
$now = new DateTimeImmutable();
$targets = anonIpTargets::build($tableNames, $now->format('Y-m-d H:i:s'));

/** @var array<string, anonIpTarget> */
$byKey = [];
foreach ($targets as $target) {
	$byKey[$target->key] = $target;
}

$makeService = static function (array $targets) use ($databaseConnection, $tableNames, $anonymizer): anonIpService {
	return new anonIpService(
		new anonIpRepository($databaseConnection, $tableNames['POST_TABLE'], $anonymizer),
		new transactionManager($databaseConnection),
		$targets
	);
};

$old = $now->modify('-2 years')->format('Y-m-d H:i:s');
$recent = $now->format('Y-m-d H:i:s');

/** Insert the fixture rows every test starts from: one aged row and one recent one per table. */
$seed = static function () use ($pdo, $tableNames, $old, $recent, $now): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach ([
		'POST_TABLE', 'ACTIONLOG_TABLE', 'SOUDANE_TABLE', 'REPORT_TABLE', 'PRIVATE_MESSAGE_TABLE',
		'BAN_APPEAL_TABLE', 'BANNER_AD_TABLE', 'LOGIN_ATTEMPT_TABLE', 'BAN_TABLE',
		'DISPLAY_IP_TABLE', 'BOARD_TABLE',
	] as $key) {
		$pdo->exec("TRUNCATE TABLE `{$tableNames[$key]}`");
	}
	$t = static fn(string $key): string => $tableNames[$key];
	$lapsed = $now->modify('-1 day')->format('Y-m-d H:i:s');
	$future = $now->modify('+1 year')->format('Y-m-d H:i:s');

	$pdo->exec("INSERT INTO `{$t('BOARD_TABLE')}` (board_uid, board_identifier, board_title, storage_directory_name)
	            VALUES (1, 'test', 'Test', 'test')");

	// posts: post_uid 1 is aged, 2 is recent.
	$pdo->exec("INSERT INTO `{$t('POST_TABLE')}`
	            (post_uid, no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host, visitor_token_hash)
	            VALUES (1, 1, 1, 't-1', 1, '{$old}', '', '', 'Anonymous', '', '', 'aged', '203.0.113.1', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
	                   (2, 2, 1, 't-1', 0, '{$recent}', '', '', 'Anonymous', '', '', 'recent', '203.0.113.2', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb')");

	$pdo->exec("INSERT INTO `{$t('ACTIONLOG_TABLE')}` (name, role, log_action, ip_address, board_uid, board_title, time_added)
	            VALUES ('a', 1, 'x', '203.0.113.1', 1, 'Test', '{$old}'),
	                   ('b', 1, 'y', '203.0.113.2', 1, 'Test', '{$recent}')");

	$pdo->exec("INSERT INTO `{$t('SOUDANE_TABLE')}` (ip_address, yeah, post_uid, date_added)
	            VALUES ('203.0.113.1', 1, 1, '{$old}'),
	                   ('203.0.113.2', 1, 2, '{$recent}')");

	$pdo->exec("INSERT INTO `{$t('REPORT_TABLE')}` (post_uid, board_uid, reporter_ip, date_reported)
	            VALUES (1, 1, '203.0.113.1', '{$old}'),
	                   (2, 1, '203.0.113.2', '{$recent}')");

	$pdo->exec("INSERT INTO `{$t('PRIVATE_MESSAGE_TABLE')}`
	            (ip_address, date_sent, sender_tripcode, sender_name, recipient_tripcode, message_subject, message_body)
	            VALUES ('203.0.113.1', '{$old}', 'a', 'A', 'b', 's', 'm'),
	                   ('203.0.113.2', '{$recent}', 'a', 'A', 'b', 's', 'm')");

	$pdo->exec("INSERT INTO `{$t('BANNER_AD_TABLE')}` (banner_file_name, ip_address, date_submitted)
	            VALUES ('a.png', '203.0.113.1', '{$old}'),
	                   ('b.png', '203.0.113.2', '{$recent}')");

	$pdo->exec("INSERT INTO `{$t('LOGIN_ATTEMPT_TABLE')}` (username, username_key, ip, attempted_at)
	            VALUES ('a', 'a', '203.0.113.1', '{$old}'),
	                   ('b', 'b', '203.0.113.2', '{$recent}')");

	// bans: 1 aged and revoked, 2 aged and lapsed, 3 aged but permanent, 4 aged wildcard,
	// 5 aged and still in force, 6 recent and revoked.
	$pdo->exec("INSERT INTO `{$t('BAN_TABLE')}` (ban_id, board_uid, ip_pattern, is_wildcard, filed_at, expires_at, revoked_at)
	            VALUES (1, 1, '203.0.113.11', 0, '{$old}', NULL, '{$lapsed}'),
	                   (2, 1, '203.0.113.12', 0, '{$old}', '{$lapsed}', NULL),
	                   (3, 1, '203.0.113.13', 0, '{$old}', NULL, NULL),
	                   (4, 1, '203.0.113.*',  1, '{$old}', '{$lapsed}', NULL),
	                   (5, 1, '203.0.113.15', 0, '{$old}', '{$future}', NULL),
	                   (6, 1, '203.0.113.16', 0, '{$recent}', NULL, '{$lapsed}')");

	$pdo->exec("INSERT INTO `{$t('BAN_APPEAL_TABLE')}` (ban_id, appellant_ip, reason, filed_at)
	            VALUES (1, '203.0.113.1', 'r', '{$old}'),
	                   (2, '203.0.113.2', 'r', '{$recent}')");

	$pdo->exec("INSERT INTO `{$t('DISPLAY_IP_TABLE')}` (post_uid, ip_part)
	            VALUES (1, ' <span class=\"userIP\">(IP: 203.0.*.*)</span>'),
	                   (2, ' <span class=\"userIP\">(IP: 203.0.*.*)</span>')");

	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$column = static function (string $table, string $column, string $where = '1') use ($pdo): array {
	return $pdo->query("SELECT `{$column}` FROM `{$table}` WHERE {$where}")->fetchAll(PDO::FETCH_COLUMN);
};

echo "IP anonymization integration tests ({$databaseName})\n\n";

// ---------------------------------------------------------------------------
// Coverage
// ---------------------------------------------------------------------------

echo "Targets\n";

testCase('every registered target names a real table and column', function () use ($pdo, $targets) {
	foreach ($targets as $target) {
		$pdo->query("SELECT `{$target->ipColumn}` FROM `{$target->table}` LIMIT 0");
	}
});

testCase('every table in the schema holding an IP is registered', function () use ($pdo, $targets, $databaseName) {
	$registered = [];
	foreach ($targets as $target) {
		$registered[] = "{$target->table}.{$target->ipColumn}";
	}

	// Anything that looks like an address column but is not a target is a gap in the registry.
	$known = [
		// A foreign key to posts.post_uid, not an address.
		'quote_links.host_post_uid',
	];

	$found = $pdo->query(
		"SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS c
		 FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = " . $pdo->quote($databaseName) . "
		 AND (COLUMN_NAME IN ('ip', 'host', 'ip_address', 'ip_part', 'ip_pattern')
		      OR COLUMN_NAME LIKE '%\\_ip')"
	)->fetchAll(PDO::FETCH_COLUMN);

	$missing = array_values(array_diff($found, $registered, $known));

	assertSameValue([], $missing, 'unregistered IP columns: ' . implode(', ', $missing));
});

// ---------------------------------------------------------------------------
// Cutoff runs
// ---------------------------------------------------------------------------

echo "\nCutoff run\n";

testCase('a cutoff run hashes the aged row and leaves the recent one alone', function () use ($seed, $makeService, $targets, $column, $tableNames, $anonymizer) {
	$seed();
	$changed = $makeService($targets)->anonymizeByTimeframe('1year');

	assertTrueValue($changed > 0, 'nothing was anonymized');

	$hashed = $anonymizer->hash('203.0.113.1');

	foreach ([
		['POST_TABLE', 'host', 'post_uid = 1', 'post_uid = 2'],
		['ACTIONLOG_TABLE', 'ip_address', "log_action = 'x'", "log_action = 'y'"],
		['SOUDANE_TABLE', 'ip_address', 'post_uid = 1', 'post_uid = 2'],
		['REPORT_TABLE', 'reporter_ip', 'post_uid = 1', 'post_uid = 2'],
		['BAN_APPEAL_TABLE', 'appellant_ip', 'ban_id = 1', 'ban_id = 2'],
		['LOGIN_ATTEMPT_TABLE', 'ip', "username = 'a'", "username = 'b'"],
		['BANNER_AD_TABLE', 'ip_address', "banner_file_name = 'a.png'", "banner_file_name = 'b.png'"],
	] as [$key, $col, $agedWhere, $recentWhere]) {
		assertSameValue([$hashed], $column($tableNames[$key], $col, $agedWhere), "{$key}.{$col} was not hashed");
		assertSameValue(['203.0.113.2'], $column($tableNames[$key], $col, $recentWhere), "{$key}.{$col} touched a recent row");
	}
});

testCase('the hash MariaDB writes is the one PHP computes', function () use ($column, $tableNames, $anonymizer) {
	assertSameValue(
		[$anonymizer->hash('203.0.113.1')],
		$column($tableNames['POST_TABLE'], 'host', 'post_uid = 1'),
		'the SQL and PHP hashes disagree, so no lookup could match an anonymized row'
	);
});

testCase('a private message ages on its own timestamp', function () use ($column, $tableNames, $anonymizer) {
	assertSameValue(
		[$anonymizer->hash('203.0.113.1'), '203.0.113.2'],
		$column($tableNames['PRIVATE_MESSAGE_TABLE'], 'ip_address'),
		'private_messages.ip_address was not anonymized by age'
	);
});

testCase('the public display fragment is cleared, not replaced by a hash', function () use ($column, $tableNames) {
	assertSameValue([''], $column($tableNames['DISPLAY_IP_TABLE'], 'ip_part', 'post_uid = 1'), 'aged fragment was not cleared');
	assertTrueValue($column($tableNames['DISPLAY_IP_TABLE'], 'ip_part', 'post_uid = 2')[0] !== '', 'recent fragment was cleared');
});

testCase('an aged post loses its browser token hash completely', function () use ($column, $tableNames) {
	$tokens = $column($tableNames['POST_TABLE'], 'visitor_token_hash', '1 ORDER BY post_uid');

	// NULL, not a hash and not '': that is what a post written before the column existed holds,
	// and what the renderer and the staff filters read as "no token".
	assertSameValue(null, $tokens[0], 'the aged post kept its token hash');
	assertSameValue('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $tokens[1], 'a recent post lost its token');
});

echo "\nBans\n";

testCase('only lapsed, non-wildcard ban patterns are anonymized', function () use ($column, $tableNames, $anonymizer) {
	$patterns = $column($tableNames['BAN_TABLE'], 'ip_pattern', '1 ORDER BY ban_id');

	assertSameValue($anonymizer->hash('203.0.113.11'), $patterns[0], 'a revoked ban was not anonymized');
	assertSameValue($anonymizer->hash('203.0.113.12'), $patterns[1], 'an expired ban was not anonymized');
	assertSameValue('203.0.113.13', $patterns[2], 'a permanent ban in force was anonymized');
	assertSameValue('203.0.113.*', $patterns[3], 'a wildcard pattern was anonymized');
	assertSameValue('203.0.113.15', $patterns[4], 'a ban still in force was anonymized');
	assertSameValue('203.0.113.16', $patterns[5], 'a ban newer than the cutoff was anonymized');
});

echo "\nIdempotence and full runs\n";

testCase('re-running the same cutoff changes nothing', function () use ($makeService, $targets) {
	assertSameValue(0, $makeService($targets)->anonymizeByTimeframe('1year'), 'a second run changed rows');
});

testCase('a full run reaches the recent rows the cutoff spared', function () use ($seed, $makeService, $targets, $column, $tableNames, $anonymizer) {
	$seed();
	$makeService($targets)->anonymizeAll();

	assertSameValue(
		[$anonymizer->hash('203.0.113.1'), $anonymizer->hash('203.0.113.2')],
		$column($tableNames['POST_TABLE'], 'host', '1 ORDER BY post_uid'),
		'a full run left a raw address behind'
	);

	// A ban still in force is protected by its guard in every mode, cutoff or not.
	assertSameValue(
		['203.0.113.13', '203.0.113.*', '203.0.113.15'],
		$column($tableNames['BAN_TABLE'], 'ip_pattern', 'ban_id IN (3, 4, 5) ORDER BY ban_id'),
		'a full run anonymized an enforceable ban'
	);
});

testCase('a second full run skips every already-anonymized row', function () use ($makeService, $targets, $column, $tableNames) {
	// What the schedule now does on every tick after the first. Changing nothing is half of it;
	// the half that matters is not hashing the hashes, which would destroy them irreversibly.
	$before = $column($tableNames['POST_TABLE'], 'host', '1 ORDER BY post_uid');

	assertSameValue(0, $makeService($targets)->anonymizeAll(), 'a second full run changed rows');
	assertSameValue($before, $column($tableNames['POST_TABLE'], 'host', '1 ORDER BY post_uid'), 'a second full run rewrote the hashes');
});

testCase('the breakdown names every target that changed rows', function () use ($seed, $makeService, $targets) {
	$seed();
	$service = $makeService($targets);
	$service->anonymizeAll();
	$breakdown = $service->getLastBreakdown();

	foreach (['posts', 'actionLog', 'soudane', 'reports', 'privateMessages', 'banAppeals',
	          'bannerAds', 'loginAttempts', 'bans', 'displayIp'] as $key) {
		assertTrueValue(($breakdown[$key] ?? 0) > 0, "target '{$key}' reported no changed rows");
	}
});

echo "\nSchedule\n";

$makeLedger = static function () use ($databaseConnection, $tableNames): anonIpRunRepository {
	return new anonIpRunRepository($databaseConnection, $tableNames['ANON_IP_RUN_TABLE']);
};

testCase('the first claim on an empty ledger succeeds', function () use ($pdo, $tableNames, $makeLedger) {
	$pdo->exec("TRUNCATE TABLE `{$tableNames['ANON_IP_RUN_TABLE']}`");

	$notBefore = (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
	$runId = $makeLedger()->claimScheduledRun($notBefore, 365);

	assertTrueValue($runId !== null && $runId > 0, 'the first scheduled run was not claimed');
});

testCase('a second claim inside the interval is refused', function () use ($makeLedger) {
	// The insert is the claim, so two requests arriving together cannot both dispatch.
	$notBefore = (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');

	assertSameValue(null, $makeLedger()->claimScheduledRun($notBefore, 365), 'the interval was not honoured');
	assertSameValue(null, $makeLedger()->claimScheduledRun($notBefore, 365), 'the interval was not honoured');
});

testCase('a claim succeeds once the interval has elapsed', function () use ($makeLedger) {
	// The ledger holds a run from a moment ago. A cutoff later than it means the interval has
	// gone by, which is what a real tick looks like a week after the last run.
	$elapsed = (new DateTimeImmutable('+1 minute'))->format('Y-m-d H:i:s');

	assertTrueValue($makeLedger()->claimScheduledRun($elapsed, 365) !== null, 'an elapsed interval did not claim');

	// And that new run blocks the next tick in its turn, the interval not yet having gone by.
	$notElapsed = (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s');
	assertSameValue(null, $makeLedger()->claimScheduledRun($notElapsed, 365), 'the fresh run did not hold the schedule off');
});

testCase('an unfinished run still counts as having gone', function () use ($pdo, $tableNames, $makeLedger) {
	// A job that died must not leave the schedule dispatching another on every request.
	$pdo->exec("TRUNCATE TABLE `{$tableNames['ANON_IP_RUN_TABLE']}`");

	$notBefore = (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
	assertTrueValue($makeLedger()->claimScheduledRun($notBefore, 365) !== null, 'the claim failed');
	assertSameValue(null, $makeLedger()->claimScheduledRun($notBefore, 365), 'an unfinished run did not hold the schedule off');
});

testCase('a discarded claim frees the interval again', function () use ($pdo, $tableNames, $makeLedger) {
	$pdo->exec("TRUNCATE TABLE `{$tableNames['ANON_IP_RUN_TABLE']}`");

	$notBefore = (new DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
	$ledger = $makeLedger();

	$runId = $ledger->claimScheduledRun($notBefore, 365);
	$ledger->discardRun((int) $runId);

	assertTrueValue($ledger->claimScheduledRun($notBefore, 365) !== null, 'the freed interval stayed claimed');
});

testCase('finishing a run records what it changed', function () use ($pdo, $tableNames, $makeLedger) {
	$pdo->exec("TRUNCATE TABLE `{$tableNames['ANON_IP_RUN_TABLE']}`");

	$ledger = $makeLedger();
	$runId = $ledger->recordManualRun(30);
	$ledger->markFinished($runId, 12, ['posts' => 7, 'reports' => 5]);

	$run = $ledger->getLastRun();

	assertSameValue('manual', $run['trigger_source'], 'the trigger source was not recorded');
	assertSameValue(30, (int) $run['older_than_days'], 'the run scope was not recorded');
	assertSameValue(12, (int) $run['rows_changed'], 'the row count was not recorded');
	assertSameValue(['posts' => 7, 'reports' => 5], json_decode($run['breakdown'], true), 'the breakdown was not recorded');
	assertTrueValue($run['finished_at'] !== null, 'the run was not closed out');
});

testCase('the newest run is the one reported', function () use ($makeLedger) {
	$ledger = $makeLedger();
	$ledger->recordManualRun(null);

	$run = $ledger->getLastRun();
	assertSameValue(null, $run['older_than_days'], 'a full-scope run should record no window');
	assertSameValue(null, $run['finished_at'], 'a just-dispatched run should not be finished');
});

echo "\nSalt\n";

testCase('anonymizing refuses to run without a salt', function () use ($databaseConnection, $tableNames, $byKey) {
	$repo = new anonIpRepository($databaseConnection, $tableNames['POST_TABLE'], new ipAnonymizer(''));

	try {
		$repo->anonymize($byKey['posts'], null);
	} catch (RuntimeException $e) {
		assertTrueValue(str_contains($e->getMessage(), 'ANON_IP_SALT'), 'wrong error: ' . $e->getMessage());
		return;
	}

	throw new RuntimeException('an unsalted, brute-forceable hash was written');
});

testCase('clearing needs no salt, since it writes no hash', function () use ($seed, $databaseConnection, $tableNames, $byKey, $column) {
	$seed();
	$repo = new anonIpRepository($databaseConnection, $tableNames['POST_TABLE'], new ipAnonymizer(''));

	assertSameValue(2, $repo->anonymize($byKey['displayIp'], null), 'clearing did not run');
	assertSameValue(['', ''], $column($tableNames['DISPLAY_IP_TABLE'], 'ip_part'), 'fragments were not cleared');
});

// ---------------------------------------------------------------------------
// Result
// ---------------------------------------------------------------------------

echo "\n";

if ($failed !== []) {
	echo "\033[31m" . count($failed) . " failed\033[0m, {$passed} passed\n\n";
	foreach ($failed as $failure) {
		echo "  \033[31m✗\033[0m {$failure}\n\n";
	}
	exit(1);
}

echo "\033[32mAll {$passed} tests passed.\033[0m\n";
exit(0);
