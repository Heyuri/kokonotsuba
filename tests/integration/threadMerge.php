<?php

/**
 * Integration tests for post ordering after a thread merge, against a real MariaDB.
 *
 * Merging reparents an older thread's posts onto a newer one, so the destination ends up holding
 * posts whose post_uid and post number are both lower than its own OP's. Every read path that
 * returns an OP alongside replies has to order on is_op rather than assume the OP sorts first,
 * which is what these tests pin: order the fixture the wrong way round and they fail.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/threadMerge.php
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

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\thread\threadRepository;

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
// Fixture: an old thread (low post numbers) and a newer one that will absorb it
// ---------------------------------------------------------------------------

$pdo->exec(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name, listed)
	 VALUES (1, 'a', 'Board A', 'a', 1)"
);

$insertThread = $pdo->prepare(
	"INSERT INTO `{$tableNames['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID, last_bump_time)
	 VALUES (:thread, :no, :uid, 1, :bumped)"
);
$insertPost = $pdo->prepare(
	"INSERT INTO `{$tableNames['POST_TABLE']}`
	 (post_uid, no, boardUID, thread_uid, post_position, is_op, root, pwd, now, name, email, sub, com, host, status)
	 VALUES (:uid, :no, 1, :thread, :pos, :is_op, :root, '', '', 'Anonymous', '', '', :com, '10.0.0.1', '')"
);

// Old thread: post_uids 1-3, numbers 100-102
$insertThread->execute([':thread' => 't-old', ':no' => 100, ':uid' => 1, ':bumped' => '2026-01-01 02:00:00']);
$insertPost->execute([':uid' => 1, ':no' => 100, ':thread' => 't-old', ':pos' => 0, ':is_op' => 1, ':root' => '2026-01-01 00:00:00', ':com' => 'old op']);
$insertPost->execute([':uid' => 2, ':no' => 101, ':thread' => 't-old', ':pos' => 1, ':is_op' => 0, ':root' => '2026-01-01 01:00:00', ':com' => 'old reply 1']);
$insertPost->execute([':uid' => 3, ':no' => 102, ':thread' => 't-old', ':pos' => 2, ':is_op' => 0, ':root' => '2026-01-01 02:00:00', ':com' => 'old reply 2']);

// New thread: post_uids 4-5, numbers 200-201. Its OP is the NEWEST post of the pair of threads.
$insertThread->execute([':thread' => 't-new', ':no' => 200, ':uid' => 4, ':bumped' => '2026-02-01 01:00:00']);
$insertPost->execute([':uid' => 4, ':no' => 200, ':thread' => 't-new', ':pos' => 0, ':is_op' => 1, ':root' => '2026-02-01 00:00:00', ':com' => 'new op']);
$insertPost->execute([':uid' => 5, ':no' => 201, ':thread' => 't-new', ':pos' => 1, ':is_op' => 0, ':root' => '2026-02-01 01:00:00', ':com' => 'new reply 1']);

$threads = new threadRepository(
	$databaseConnection,
	$tableNames['POST_TABLE'],
	$tableNames['THREAD_TABLE'],
	$tableNames['THREAD_THEMES_TABLE'],
	$tableNames['DELETED_POSTS_TABLE'],
	$tableNames['FILE_TABLE'],
	$tableNames['ACCOUNT_TABLE'],
	$tableNames['SOUDANE_TABLE'],
	$tableNames['NOTE_TABLE'],
);

$comments = static fn(?array $posts): array => array_map(fn($p) => $p->getComment(), $posts ?? []);

echo "Thread merge post ordering ({$nameMatch[1]})\n\n";

echo "Before the merge\n";

testCase('a thread reads OP first', function () use ($threads, $comments) {
	assertSameValue(['new op', 'new reply 1'], $comments($threads->getPostsFromThread('t-new')), 'unmerged thread order');
});

// ---------------------------------------------------------------------------
// The merge: t-old is absorbed into t-new
// ---------------------------------------------------------------------------

$threads->reparentPostsToThread(['t-old'], 't-new');
$threads->deleteThreadByUID('t-old');
$threads->reindexPostPositions('t-new');

echo "\nAfter merging an older thread in\n";

testCase('the merge leaves exactly one is_op post in the thread', function () use ($pdo, $tableNames) {
	$count = (int)$pdo->query("SELECT SUM(is_op) FROM `{$tableNames['POST_TABLE']}` WHERE thread_uid = 't-new'")->fetchColumn();
	assertSameValue(1, $count, 'is_op count after merge');
});

testCase('the OP is no longer the thread\'s lowest post_uid', function () use ($pdo, $tableNames) {
	$opUid  = (int)$pdo->query("SELECT post_uid FROM `{$tableNames['POST_TABLE']}` WHERE thread_uid = 't-new' AND is_op = 1")->fetchColumn();
	$minUid = (int)$pdo->query("SELECT MIN(post_uid) FROM `{$tableNames['POST_TABLE']}` WHERE thread_uid = 't-new'")->fetchColumn();
	assertSameValue(true, $opUid > $minUid, "OP uid {$opUid} should be above the thread minimum {$minUid} - the fixture is not exercising the bug");
});

testCase('getPostsFromThread renders the OP above the absorbed replies', function () use ($threads, $comments) {
	assertSameValue(
		['new op', 'old op', 'old reply 1', 'old reply 2', 'new reply 1'],
		$comments($threads->getPostsFromThread('t-new')),
		'merged thread render order'
	);
});

testCase('getAllPostsFromThread puts the OP first', function () use ($threads, $comments) {
	assertSameValue('new op', $comments($threads->getAllPostsFromThread('t-new'))[0] ?? null, 'first post of getAllPostsFromThread');
});

testCase('getVisiblePostsFromDeletedThread puts the OP first', function () use ($threads, $comments) {
	assertSameValue('new op', $comments($threads->getVisiblePostsFromDeletedThread('t-new'))[0] ?? null, 'first post of getVisiblePostsFromDeletedThread');
});

testCase('getFirstPostsFromThreads returns the OP, not the oldest absorbed post', function () use ($threads) {
	$first = $threads->getFirstPostsFromThreads(['t-new']);
	assertSameValue('new op', $first['t-new']?->getComment(), 'opening post of a merged thread');
});

testCase('the index preview shows the OP first', function () use ($threads, $comments) {
	$threadRows = $threads->getThreadsFromBoard(1, 10) ?? [];
	$posts = $threads->getPostsForThreads($threadRows, 3);
	assertSameValue('new op', $comments($posts)[0] ?? null, 'first post of the index preview');
});

echo "\n";

if ($failed) {
	echo "\033[31m" . count($failed) . " failed\033[0m, {$passed} passed\n";
	exit(1);
}

echo "\033[32mAll {$passed} tests passed.\033[0m\n";
exit(0);
