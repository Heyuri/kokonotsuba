<?php

/**
 * Integration tests for the lockDown module's post lookups against a real MariaDB, and its marker file.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/lockDown.php
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
require_once $root . '/code/Kokonotsuba/libraries/lib_database.php';
require_once $root . '/code/Kokonotsuba/libraries/lib_query.php';
require_once $root . '/code/Puchiko/file.php';
require_once $root . '/module/lockDown/lockDownRepository.php';
require_once $root . '/module/lockDown/lockDownFlag.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\Modules\lockDown\lockDownFlag;
use Kokonotsuba\Modules\lockDown\lockDownRepository;

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
// Connection and schema
// ---------------------------------------------------------------------------

$dsn = getenv('KOKO_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4';
$user = getenv('KOKO_TEST_USER') ?: 'koko_test';
$pass = getenv('KOKO_TEST_PASS') ?: 'kokotest';

preg_match('/host=([^;]+)/', $dsn, $hostMatch);
preg_match('/dbname=([^;]+)/', $dsn, $nameMatch);
preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);

if (!isset($hostMatch[1], $nameMatch[1])) {
	fwrite(STDERR, "KOKO_TEST_DSN must carry host= and dbname=.\n");
	exit(2);
}

$databaseName = $nameMatch[1];

try {
	$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
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

$db = databaseConnection::getInstance();
$tableNames = require $root . '/tables.php';

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

(new migrationRunner(
	$db,
	new migrationLedger($db, $tableNames['SCHEMA_MIGRATION_TABLE']),
	new schemaInspector($db, $databaseName),
	$tableNames,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (string $message, string $level): void {}
))->up();

$t = static fn(string $key): string => $tableNames[$key];

// ---------------------------------------------------------------------------
// Fixtures: one board, one thread; posts an hour old, one of them deleted
// ---------------------------------------------------------------------------

const OLD_HASH = 'aaaaaaaaaaaaaaaa';
const NEW_HASH = 'bbbbbbbbbbbbbbbb';
const DELETED_HASH = 'cccccccccccccccc';

$hourAgo = gmdate('Y-m-d H:i:s', time() - 3600);

$pdo->exec("INSERT INTO `{$t('BOARD_TABLE')}` (board_uid, board_identifier, board_title, storage_directory_name) VALUES (1, 'test', 'Test', 'test'), (2, 'other', 'Other', 'other')");
$pdo->exec("INSERT INTO `{$t('THREAD_TABLE')}` (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('t1', 1, 1, 1)");
$insert = $pdo->prepare("INSERT INTO `{$t('POST_TABLE')}`
	(post_uid, no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host, visitor_token_hash, status, capcode)
	VALUES (?, ?, 1, 't1', ?, ?, '', '', 'Anonymous', '', '', 'x', ?, ?, '', '')");
$insert->execute([1, 1, 1, $hourAgo, '203.0.113.1', OLD_HASH]);
$insert->execute([2, 2, 0, $hourAgo, '203.0.113.2', DELETED_HASH]);
$insert->execute([3, 3, 0, $hourAgo, 'ffffffffffffffffffffffffffffffff', '']);
$pdo->exec("INSERT INTO `{$t('DELETED_POSTS_TABLE')}` (post_uid, deleted_by, file_only, by_proxy) VALUES (2, NULL, 0, 0)");

$repository = new lockDownRepository($db, $t('POST_TABLE'), $t('DELETED_POSTS_TABLE'));

testCase('the marker starts absent, locks once, and unlocks', function () {
	$dir = sys_get_temp_dir() . '/koko_lockdown_' . bin2hex(random_bytes(4)) . '/';
	mkdir($dir);
	$flag = new lockDownFlag($dir);

	assertSameValue(null, $flag->read(), 'unlocked at first');
	assertTrueValue($flag->start('mod'), 'start writes');
	$first = $flag->read();
	assertSameValue('mod', $first['started_by'], 'starter recorded');
	$flag->start('other');
	assertSameValue($first, $flag->read(), 'a second start keeps the first');
	assertSameValue([], glob($dir . '*.tmp'), 'no temporary files left');
	$flag->end();
	assertSameValue(null, $flag->read(), 'unlocked again');
	rmdir($dir);
});

testCase('a token that posted before lock-down passes, others do not', function () use ($repository) {
	$now = gmdate('Y-m-d H:i:s');
	assertTrueValue($repository->tokenPostedBefore(1, OLD_HASH, $now), 'old token');
	assertTrueValue(!$repository->tokenPostedBefore(1, NEW_HASH, $now), 'new token');
	assertTrueValue(!$repository->tokenPostedBefore(2, OLD_HASH, $now), 'other board');
});

testCase('posts after lock-down began do not count', function () use ($repository, $hourAgo) {
	$before = gmdate('Y-m-d H:i:s', strtotime($hourAgo . ' UTC') - 60);
	assertTrueValue(!$repository->tokenPostedBefore(1, OLD_HASH, $before), 'post is newer than the lock-down');
});

testCase('deleted posts do not count', function () use ($repository) {
	$now = gmdate('Y-m-d H:i:s');
	assertTrueValue(!$repository->tokenPostedBefore(1, DELETED_HASH, $now), 'deleted by token');
	assertTrueValue(!$repository->hostPostedBefore(1, ['203.0.113.2'], $now), 'deleted by host');
});

testCase('the address fallback matches either stored form', function () use ($repository) {
	$now = gmdate('Y-m-d H:i:s');
	assertTrueValue($repository->hostPostedBefore(1, ['203.0.113.1'], $now), 'plain address');
	assertTrueValue($repository->hostPostedBefore(1, ['198.51.100.9', 'ffffffffffffffffffffffffffffffff'], $now), 'anonymized form');
	assertTrueValue(!$repository->hostPostedBefore(1, ['198.51.100.9'], $now), 'stranger');
	assertTrueValue(!$repository->hostPostedBefore(1, [], $now), 'no forms');
});

// ---------------------------------------------------------------------------

echo "\n{$passed} passed";
if ($failed) {
	echo ', ' . count($failed) . " failed\n\n";
	foreach ($failed as $f) {
		echo "  - {$f}\n";
	}
	exit(1);
}
echo "\n";
exit(0);
