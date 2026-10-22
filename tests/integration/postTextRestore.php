<?php

/**
 * Integration test for putting converted posts back from a pre-conversion backup.
 *
 * Seeds legacy HTML posts, snapshots the table the way a dump would, converts them with
 * legacyPostConverter, then checks legacyPostRestorer restores exactly the rows it should: the
 * untouched conversions come back as their original HTML, a post edited since is skipped unless
 * forced, and rows the backup never held as legacy (new posts, already-plain rows) are left alone.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/postTextRestore.php
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
require_once $root . '/paths.php';

use Kokonotsuba\config\configRepository;
use Kokonotsuba\config\configService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\post\legacyPostConverter;
use Kokonotsuba\post\legacyPostRestorer;
use Kokonotsuba\post\textFormat;

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

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

$dsn = getenv('KOKO_TEST_DSN');
$user = getenv('KOKO_TEST_USER') ?: '';
$pass = getenv('KOKO_TEST_PASS') ?: '';

if ($dsn === false || $dsn === '') {
	fwrite(STDERR, "KOKO_TEST_DSN is not set; skipping.\n");
	exit(2);
}

try {
	$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
	fwrite(STDERR, "could not connect: {$e->getMessage()}\n");
	exit(2);
}

preg_match('/host=([^;]+)/', $dsn, $hostMatch);
preg_match('/dbname=([^;]+)/', $dsn, $nameMatch);
preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);
$databaseName = $nameMatch[1] ?? '';

databaseConnection::createInstance([
	'DATABASE_DRIVER' => 'mysql',
	'DATABASE_HOST' => $hostMatch[1] ?? '127.0.0.1',
	'DATABASE_NAME' => $databaseName,
	'DATABASE_CHARSET' => $charsetMatch[1] ?? 'utf8mb4',
	'DATABASE_USERNAME' => $user,
	'DATABASE_PASSWORD' => $pass,
]);
$databaseConnection = databaseConnection::getInstance();
$tableNames = require $root . '/tables.php';

$postTable = $tableNames['POST_TABLE'];
$backupTable = $postTable . '_backup';

// ---------------------------------------------------------------------------
// Schema and seed
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

echo "\nPost text restore integration tests ({$databaseName})\n\n";

$pdo->exec("INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name) VALUES (1, 'b', 'Test', 'b')");

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$seed = static function (int $no, string $com, string $name, int $format) use ($pdo, $postTable): int {
	$statement = $pdo->prepare(
		"INSERT INTO `{$postTable}` (no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host, text_format)
		 VALUES (?, 1, 't1', 1, NOW(), '', '', ?, '', '', ?, '127.0.0.1', ?)"
	);
	$statement->execute([$no, $name, $com, $format]);

	return (int)$pdo->lastInsertId();
};

$untouched = $seed(1, 'Hello &lt;3<br />world', 'Anonymous', textFormat::LEGACY_HTML->value);
$edited = $seed(2, '<a href="http://x.test">http://x.test</a>', 'Anonymous', textFormat::LEGACY_HTML->value);
$oldPlain = $seed(3, "already <plain>", 'Anonymous', textFormat::PLAIN_TEXT->value);

// The dump: the table as it stood before conversion, text_format and all.
$pdo->exec("CREATE TABLE `{$backupTable}` AS SELECT * FROM `{$postTable}`");

$converter = new legacyPostConverter(
	$databaseConnection,
	new configService(new configRepository($databaseConnection, $tableNames['BOARD_CONFIG_TABLE'])),
	$postTable,
	$tableNames['BOARD_TABLE']
);
$converter->convert(false);

// What happened after the conversion: one post edited, one new post made.
$pdo->exec("UPDATE `{$postTable}` SET com = 'http://x.test (fixed)' WHERE post_uid = {$edited}");
$newPost = $seed(4, 'new <post>', 'Anonymous', textFormat::PLAIN_TEXT->value);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$restorer = new legacyPostRestorer($databaseConnection, $pdo, $backupTable, $converter, $postTable);

$readRow = static fn(int $uid): array => $pdo->query("SELECT com, text_format FROM `{$postTable}` WHERE post_uid = {$uid}")->fetch(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

testCase('the summary separates restorable rows from edited ones', function () use ($restorer) {
	assertSameValue([1 => ['restorable' => 1, 'edited' => 1]], $restorer->summary(), 'summary');
});

testCase('a dry run writes nothing', function () use ($restorer, $readRow, $untouched) {
	$result = $restorer->restore(true);

	assertSameValue(1, $result['restored'], 'dry run count');
	assertSameValue(textFormat::PLAIN_TEXT->value, (int)$readRow($untouched)['text_format'], 'dry run wrote');
});

testCase('an unedited conversion is put back as its original HTML', function () use ($restorer, $readRow, $untouched) {
	$result = $restorer->restore(false);

	assertSameValue(1, $result['restored'], 'restored count');
	assertSameValue([1], $result['boards'], 'boards touched');

	$row = $readRow($untouched);
	assertSameValue('Hello &lt;3<br />world', $row['com'], 'original markup');
	assertSameValue(textFormat::LEGACY_HTML->value, (int)$row['text_format'], 'flag back to legacy');
});

testCase('a post edited since conversion is skipped and reported', function () use ($restorer, $readRow, $edited) {
	$result = $restorer->restore(false);

	assertSameValue(0, $result['restored'], 'nothing left to restore without force');
	assertSameValue([$edited], array_map(static fn(array $r): int => (int)$r['post_uid'], $result['skipped']), 'skipped list');
	assertSameValue('http://x.test (fixed)', $readRow($edited)['com'], 'the edit survived');
});

testCase('rows the backup never held as legacy are left alone', function () use ($readRow, $oldPlain, $newPost) {
	$plain = static fn(array $row): array => ['com' => $row['com'], 'text_format' => (int)$row['text_format']];

	assertSameValue(['com' => 'already <plain>', 'text_format' => textFormat::PLAIN_TEXT->value], $plain($readRow($oldPlain)), 'already-plain row');
	assertSameValue(['com' => 'new <post>', 'text_format' => textFormat::PLAIN_TEXT->value], $plain($readRow($newPost)), 'post made after the backup');
});

testCase('--force overwrites the edit with the backup', function () use ($restorer, $readRow, $edited) {
	$result = $restorer->restore(false, true);

	assertSameValue(1, $result['restored'], 'forced restore count');

	$row = $readRow($edited);
	assertSameValue('<a href="http://x.test">http://x.test</a>', $row['com'], 'backup markup');
	assertSameValue(textFormat::LEGACY_HTML->value, (int)$row['text_format'], 'flag back to legacy');
});

testCase('a backup without text_format treats every row as legacy', function () use ($pdo, $backupTable, $databaseConnection, $converter, $postTable, $restorer) {
	$pdo->exec("ALTER TABLE `{$backupTable}` DROP COLUMN text_format");
	$old = new legacyPostRestorer($databaseConnection, $pdo, $backupTable, $converter, $postTable);

	// The already-plain row is now a candidate. Converting it would strip its brackets, which
	// does not match what is stored, so it reads as edited: reported, and never written unforced.
	assertSameValue([1 => ['restorable' => 0, 'edited' => 1]], $old->summary(), 'summary without the flag column');
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

echo "{$passed} passed\n";
exit(0);
