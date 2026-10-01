<?php

/**
 * Integration tests for the data carried across a thread copy, against a real MariaDB.
 *
 * Moving or merging with a shadow thread duplicates posts rather than moving them, so votes,
 * flags, notes and the thread theme have to be re-keyed onto the copies or they stay behind with
 * the shadow. These tests pin that, and the rule that a destination keeps whatever it already has.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/threadExtrasCarry.php
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

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\thread\threadExtrasService;

$passed = 0;
$failed = [];

function testCase(string $name, callable $fn): void {
	global $passed, $failed;

	try {
		$fn();
		$passed++;
		echo "  \033[32m✓\033[0m {$name}\n";
	} catch (Throwable $e) {
		$failed[] = $name;
		echo "  \033[31m✗\033[0m {$name}\n      {$e->getMessage()}\n";
	}
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
	if ($expected !== $actual) {
		throw new RuntimeException($message . "\n      expected: " . json_encode($expected) . "\n      actual:   " . json_encode($actual));
	}
}

$dsn  = getenv('KOKO_TEST_DSN') ?: '';
$user = getenv('KOKO_TEST_USER') ?: '';
$pass = getenv('KOKO_TEST_PASS') ?: '';

if ($dsn === '' || !preg_match('/host=([^;]+)/', $dsn, $hostMatch) || !preg_match('/dbname=([^;]+)/', $dsn, $nameMatch)) {
	fwrite(STDERR, "KOKO_TEST_DSN must be set and contain host= and dbname=.\n");
	exit(2);
}

preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);

try {
	$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
	fwrite(STDERR, "No database reachable: {$e->getMessage()}\n");
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

// ---------------------------------------------------------------------------
// Fixture: a source thread and its copy, as a shadow move leaves them
// ---------------------------------------------------------------------------

$pdo->exec(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name, listed)
	 VALUES (1, 'a', 'Board A', 'a', 1), (2, 'b', 'Board B', 'b', 1)"
);

$insertThread = $pdo->prepare(
	"INSERT INTO `{$tableNames['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID)
	 VALUES (:thread, :no, :uid, :board)"
);
$insertPost = $pdo->prepare(
	"INSERT INTO `{$tableNames['POST_TABLE']}`
	 (post_uid, no, boardUID, thread_uid, post_position, is_op, root, pwd, now, name, email, sub, com, host, status)
	 VALUES (:uid, :no, :board, :thread, :pos, :is_op, '2026-01-01 00:00:00', '', '', 'Anonymous', '', '', :com, '10.0.0.1', '')"
);

// source thread on board 1, post uids 1-2
$insertThread->execute([':thread' => 't-src', ':no' => 100, ':uid' => 1, ':board' => 1]);
$insertPost->execute([':uid' => 1, ':no' => 100, ':board' => 1, ':thread' => 't-src', ':pos' => 0, ':is_op' => 1, ':com' => 'op']);
$insertPost->execute([':uid' => 2, ':no' => 101, ':board' => 1, ':thread' => 't-src', ':pos' => 1, ':is_op' => 0, ':com' => 'reply']);

// the copy a shadow move made on board 2, post uids 3-4
$insertThread->execute([':thread' => 't-copy', ':no' => 500, ':uid' => 3, ':board' => 2]);
$insertPost->execute([':uid' => 3, ':no' => 500, ':board' => 2, ':thread' => 't-copy', ':pos' => 0, ':is_op' => 1, ':com' => 'op']);
$insertPost->execute([':uid' => 4, ':no' => 501, ':board' => 2, ':thread' => 't-copy', ':pos' => 1, ':is_op' => 0, ':com' => 'reply']);

// a thread that already carries a theme of its own, for the merge case
$insertThread->execute([':thread' => 't-themed', ':no' => 900, ':uid' => 5, ':board' => 2]);
$insertPost->execute([':uid' => 5, ':no' => 900, ':board' => 2, ':thread' => 't-themed', ':pos' => 0, ':is_op' => 1, ':com' => 'op']);

// Yeahs: two on the OP, one Nope on the reply
$pdo->exec(
	"INSERT INTO `{$tableNames['SOUDANE_TABLE']}` (ip_address, yeah, post_uid) VALUES
	 ('10.0.0.1', 1, 1), ('10.0.0.2', 1, 1), ('10.0.0.3', 0, 2)"
);

$pdo->exec("INSERT INTO `{$tableNames['COUNTRY_FLAG_TABLE']}` (post_uid, country) VALUES (1, 'jp')");
$pdo->exec("INSERT INTO `{$tableNames['DISPLAY_IP_TABLE']}` (post_uid, ip_part) VALUES (2, '10.0.0.x')");
$pdo->exec("INSERT INTO `{$tableNames['NOTE_TABLE']}` (post_uid, note_text) VALUES (1, 'keep an eye on this')");

$insertTheme = $pdo->prepare(
	"INSERT INTO `{$tableNames['THREAD_THEMES_TABLE']}` (thread_uid, background_hex_color, raw_styling)
	 VALUES (:thread, :bg, :raw)"
);
$insertTheme->execute([':thread' => 't-src', ':bg' => '#112233', ':raw' => 'body{}']);
$insertTheme->execute([':thread' => 't-themed', ':bg' => '#ffffff', ':raw' => 'p{}']);

$extras = new threadExtrasService(
	$databaseConnection,
	$tableNames['SOUDANE_TABLE'],
	$tableNames['COUNTRY_FLAG_TABLE'],
	$tableNames['DISPLAY_IP_TABLE'],
	$tableNames['NOTE_TABLE'],
	$tableNames['THREAD_THEMES_TABLE'],
);

$countFor = static function (string $table, string $column, mixed $value) use ($pdo, $tableNames): int {
	$statement = $pdo->prepare("SELECT COUNT(*) FROM `{$tableNames[$table]}` WHERE `{$column}` = :value");
	$statement->execute([':value' => $value]);
	return (int)$statement->fetchColumn();
};

$valueFor = static function (string $table, string $select, string $column, mixed $value) use ($pdo, $tableNames): mixed {
	$statement = $pdo->prepare("SELECT `{$select}` FROM `{$tableNames[$table]}` WHERE `{$column}` = :value");
	$statement->execute([':value' => $value]);
	return $statement->fetchColumn();
};

echo "Thread copy carry-over ({$nameMatch[1]})\n\n";

// post_uid 1 -> 3, post_uid 2 -> 4, as copyThreadAndPosts reports it
$extras->copyPostExtras([1 => 3, 2 => 4]);
$extras->copyThreadTheme('t-src', 't-copy');

echo "After a shadow move\n";

testCase('Yeahs follow the OP onto its copy', function () use ($countFor) {
	assertSameValue(2, $countFor('SOUDANE_TABLE', 'post_uid', 3), 'votes on the copied OP');
});

testCase('the original keeps its own Yeahs', function () use ($countFor) {
	assertSameValue(2, $countFor('SOUDANE_TABLE', 'post_uid', 1), 'votes on the original OP');
});

testCase('a vote keeps its value and voter', function () use ($valueFor, $pdo, $tableNames) {
	assertSameValue('0', (string)$valueFor('SOUDANE_TABLE', 'yeah', 'post_uid', 4), 'the Nope stayed a Nope');
	assertSameValue('10.0.0.3', (string)$valueFor('SOUDANE_TABLE', 'ip_address', 'post_uid', 4), 'voter address');
});

testCase('the country flag follows the post', function () use ($valueFor) {
	assertSameValue('jp', (string)$valueFor('COUNTRY_FLAG_TABLE', 'country', 'post_uid', 3), 'copied flag');
});

testCase('the shown address follows the post', function () use ($valueFor) {
	assertSameValue('10.0.0.x', (string)$valueFor('DISPLAY_IP_TABLE', 'ip_part', 'post_uid', 4), 'copied ip part');
});

testCase('staff notes follow the post', function () use ($valueFor) {
	assertSameValue('keep an eye on this', (string)$valueFor('NOTE_TABLE', 'note_text', 'post_uid', 3), 'copied note');
});

testCase('the theme follows the thread', function () use ($valueFor) {
	assertSameValue('#112233', (string)$valueFor('THREAD_THEMES_TABLE', 'background_hex_color', 'thread_uid', 't-copy'), 'copied theme');
});

echo "\nRun twice, and on a destination that already has its own\n";

testCase('copying again does not double the Yeahs', function () use ($extras, $countFor) {
	$extras->copyPostExtras([1 => 3, 2 => 4]);
	assertSameValue(2, $countFor('SOUDANE_TABLE', 'post_uid', 3), 'votes after a second copy');
});

testCase('merging into a themed thread leaves its theme alone', function () use ($extras, $valueFor, $countFor) {
	$extras->copyThreadTheme('t-src', 't-themed');
	assertSameValue('#ffffff', (string)$valueFor('THREAD_THEMES_TABLE', 'background_hex_color', 'thread_uid', 't-themed'), 'destination theme');
	assertSameValue(1, $countFor('THREAD_THEMES_TABLE', 'thread_uid', 't-themed'), 'one theme per thread');
});

testCase('an empty map is a no-op', function () use ($extras, $countFor) {
	$extras->copyPostExtras([]);
	assertSameValue(2, $countFor('SOUDANE_TABLE', 'post_uid', 3), 'votes after an empty copy');
});

echo "\n";

if ($failed) {
	echo "\033[31m" . count($failed) . " failed\033[0m, {$passed} passed\n";
	exit(1);
}

echo "\033[32mAll {$passed} passed\033[0m\n";
exit(0);
