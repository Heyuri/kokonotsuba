<?php

/**
 * Integration tests for bumpLimitSweeper against a real MariaDB: which threads a sweep finds as
 * having just outlived MAX_AGE_TIME, how often it sweeps, and which static pages it rebuilds.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/bumpLimitSweep.php
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
require_once $root . '/code/Kokonotsuba/libraries/lib_post.php';

use Kokonotsuba\board\board;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\thread\bumpLimitSweeper;
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

/** A board with just the config, storage and rebuild the sweeper touches. */
class sweepBoard extends board {
	public array $config = [];
	public string $storagePath = '';
	/** @var int[] */
	public array $rebuiltPages = [];

	public static function make(array $config, string $storagePath): self {
		$board = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
		$board->config = $config;
		$board->storagePath = $storagePath;
		return $board;
	}

	public function getBoardUID(): int { return 1; }
	public function getBoardStoragePath(): string { return $this->storagePath; }
	public function getConfigValue(string $key, $default = null, bool $throwOnMissing = false): mixed {
		return $this->config[$key] ?? $default;
	}
	public function rebuildBoardPage(int $pageNumber, bool $logRebuild = false): void {
		$this->rebuiltPages[] = $pageNumber;
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
// Fixtures: five threads, two to a page, three of them turning old between two sweeps
// ---------------------------------------------------------------------------

const T0 = 1_800_000_000;
const T2 = T0 + 600;
const HOUR = 3600;

$at = static fn(int $time): string => date('Y-m-d H:i:s', $time);

$pdo->exec("INSERT INTO `{$t('BOARD_TABLE')}` (board_uid, board_identifier, board_title, storage_directory_name) VALUES (1, 'test', 'Test', 'test')");
$insert = $pdo->prepare("INSERT INTO `{$t('THREAD_TABLE')}`
	(thread_uid, post_op_number, post_op_post_uid, boardUID, thread_created_time, last_bump_time, last_reply_time)
	VALUES (?, ?, ?, 1, ?, ?, ?)");

// uid => [created, last bump]; index order by bump is a b | c d | e
$threads = [
	'a' => [T0 - HOUR + 100, T2 - 10],
	'b' => [T0 - HOUR - 100, T2 - 20],
	'c' => [T0 - HOUR + 300, T2 - 30],
	'd' => [T2 - 100, T2 - 40],
	'e' => [T0 - HOUR + 500, T2 - 50],
];
$n = 0;
foreach ($threads as $uid => [$created, $bump]) {
	$n++;
	$insert->execute([$uid, $n, $n, $at($created), $at($bump), $at($bump)]);
}

$threadRepository = new threadRepository($db, $t('POST_TABLE'), $t('THREAD_TABLE'), $t('THREAD_THEMES_TABLE'), $t('DELETED_POSTS_TABLE'), $t('FILE_TABLE'), $t('ACCOUNT_TABLE'), $t('SOUDANE_TABLE'), $t('NOTE_TABLE'), $t('COUNTRY_FLAG_TABLE'), $t('DISPLAY_IP_TABLE'), $t('REPORT_TABLE'));

$storageRoot = sys_get_temp_dir() . '/koko_bump_sweep_' . getmypid();
$newBoard = static function (array $config, string $name) use ($storageRoot): sweepBoard {
	return sweepBoard::make($config + ['MAX_AGE_TIME' => 1, 'PAGE_DEF' => 2, 'STATIC_HTML_UNTIL' => 2], "{$storageRoot}/{$name}/");
};

// ---------------------------------------------------------------------------

echo "bumpLimitSweeper\n";

testCase('the repository finds threads created inside the window only', function () use ($threadRepository, $at) {
	$uids = $threadRepository->getThreadUidsCreatedBetween(1, $at(T0 - HOUR), $at(T2 - HOUR));
	sort($uids);
	assertSameValue(['a', 'c', 'e'], $uids, 'created in [T0 - 1h, T2 - 1h)');
});

testCase('the first tick only records, a tick inside the interval does nothing', function () use ($newBoard, $threadRepository) {
	$board = $newBoard([], 'first');
	$sweeper = new bumpLimitSweeper($board, $threadRepository);
	$sweeper->tick(T0);
	$sweeper->tick(T0 + 30);
	assertSameValue([], $board->rebuiltPages, 'nothing rebuilt');
	assertSameValue((string)T0, file_get_contents($board->storagePath . 'cache/bump_limit_sweep'), 'sweep time recorded');
});

testCase('a due tick rebuilds the static pages holding threads that turned old', function () use ($newBoard, $threadRepository) {
	$board = $newBoard([], 'due');
	$sweeper = new bumpLimitSweeper($board, $threadRepository);
	$sweeper->tick(T0);
	$sweeper->tick(T2);
	assertSameValue([1, 2], $board->rebuiltPages, 'pages of a and c; e is past the static limit');

	$sweeper->tick(T2 + 120);
	assertSameValue([1, 2], $board->rebuiltPages, 'nothing new turned old since');
});

testCase('every page is static at STATIC_HTML_UNTIL -1, none at 0', function () use ($newBoard, $threadRepository) {
	$all = $newBoard(['STATIC_HTML_UNTIL' => -1], 'all');
	$none = $newBoard(['STATIC_HTML_UNTIL' => 0], 'none');
	foreach ([$all, $none] as $board) {
		$sweeper = new bumpLimitSweeper($board, $threadRepository);
		$sweeper->tick(T0);
		$sweeper->tick(T2);
	}
	assertSameValue([1, 2, 3], $all->rebuiltPages, 'page of e too');
	assertSameValue([], $none->rebuiltPages, 'no static pages');
});

testCase('no bump age limit means no sweep and no state file', function () use ($newBoard, $threadRepository) {
	$board = $newBoard(['MAX_AGE_TIME' => 0], 'off');
	$sweeper = new bumpLimitSweeper($board, $threadRepository);
	$sweeper->tick(T0);
	$sweeper->tick(T2);
	assertSameValue([], $board->rebuiltPages, 'nothing rebuilt');
	assertSameValue(false, file_exists($board->storagePath . 'cache/bump_limit_sweep'), 'no state file');
});

exec('rm -rf ' . escapeshellarg($storageRoot));

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
