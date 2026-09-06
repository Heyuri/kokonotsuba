<?php

/**
 * Integration tests for the per-post text format, against a real MariaDB.
 *
 * The unit suite covers the conversions themselves. This covers the part only a live engine can
 * settle: that `posts.text_format` is written by the insert, read back by Post, survives a thread
 * move, and - most of all - that a row inserted by an older build with no value defaults to
 * legacy, so nothing already in the table starts being escaped a second time.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/postTextFormat.php
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
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\post\legacyTextConverter;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\postRegistData;
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

$postTable = $tableNames['POST_TABLE'];
$boardTable = $tableNames['BOARD_TABLE'];
$threadTable = $tableNames['THREAD_TABLE'];

// ---------------------------------------------------------------------------
// Schema
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

echo "Post text format integration tests ({$databaseName})\n\n";

// A board and a thread for the posts to hang off.
$databaseConnection->execute(
	"INSERT INTO {$boardTable} (board_uid, board_identifier, board_title, storage_directory_name) VALUES (1, 'b', 'Test', 'b')"
);
$databaseConnection->execute(
	"INSERT INTO {$threadTable} (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('t1', 1, 1, 1)"
);

$nextNo = 0;

/** Insert a post through the same DTO and query the app uses. */
$insert = function (textFormat $format, string $comment = 'body', string $name = 'Anon') use (
	$databaseConnection, $postTable, &$nextNo
): int {
	$nextNo++;

	$data = new postRegistData(
		$nextNo, '', 't1', 0, '', '', 'pw', '25/01/01(Wed)00:00',
		$name, '', '', '', 'mail@test', 'subject', $comment, '127.0.0.1', false, '', $format
	);

	$params = $data->toParams(1, '2025-01-01 00:00:00');

	$databaseConnection->execute("
		INSERT INTO {$postTable}
			(no, poster_hash, boardUID, thread_uid, post_position, is_op, root, category, tag, pwd, now,
			name, tripcode, secure_tripcode, capcode, email, sub, com, host, visitor_token_hash, status, text_format)
		VALUES (:no, :poster_hash, :boardUID, :thread_uid, :post_position, :is_op, :root,
			:category, :tag, :pwd, :now, :name, :tripcode, :secure_tripcode, :capcode, :email, :sub, :com, :host, :visitor_token_hash, :status, :text_format)
	", $params);

	return (int)$databaseConnection->lastInsertId();
};

/** Read a post back the way the app does: SELECT p.*, wrapped in Post. */
$read = function (int $postUid) use ($databaseConnection, $postTable): Post {
	return new Post($databaseConnection->fetchOne("SELECT p.* FROM {$postTable} p WHERE post_uid = ?", [$postUid]));
};

// ---------------------------------------------------------------------------

echo "Storage\n";

testCase('a plain-text post reads back as plain text', function () use ($insert, $read) {
	$post = $read($insert(textFormat::PLAIN_TEXT));

	assertSameValue(textFormat::PLAIN_TEXT, $post->getTextFormat(), 'format did not survive the round trip');
});

testCase('a raw-html post reads back as raw html', function () use ($insert, $read) {
	$post = $read($insert(textFormat::RAW_HTML));

	assertSameValue(textFormat::RAW_HTML, $post->getTextFormat(), 'format did not survive the round trip');
});

testCase('a comment is stored byte for byte, markup and all', function () use ($insert, $read) {
	$typed = "<b>not bold</b> & \"quoted\"\nsecond line";
	$post = $read($insert(textFormat::PLAIN_TEXT, $typed));

	assertSameValue($typed, $post->getComment(), 'the comment was altered on its way through the database');
});

testCase('a newline survives the column', function () use ($insert, $read) {
	$post = $read($insert(textFormat::PLAIN_TEXT, "one\ntwo"));

	assertSameValue("one\ntwo", $post->getComment(), 'newlines are what carry line structure now');
});

// ---------------------------------------------------------------------------

echo "\nRows from before the column existed\n";

testCase('a row inserted without the column defaults to legacy', function () use ($databaseConnection, $postTable, $read, &$nextNo) {
	// Exactly what an older build's INSERT looked like: no text_format in the column list.
	$nextNo++;
	$databaseConnection->execute("
		INSERT INTO {$postTable} (no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host)
		VALUES (?, 1, 't1', 0, '2025-01-01 00:00:00', 'pw', 'now', 'Anon', '', '', 'a<br>b', '127.0.0.1')
	", [$nextNo]);

	$post = $read((int)$databaseConnection->lastInsertId());

	assertSameValue(textFormat::LEGACY_HTML, $post->getTextFormat(), 'an untouched row must not start being escaped');
});

testCase('the column default is legacy at the database level', function () use ($databaseConnection, $postTable) {
	$column = $databaseConnection->fetchOne("SHOW COLUMNS FROM {$postTable} LIKE 'text_format'");

	assertSameValue('0', (string)$column['Default'], 'the default decides what every pre-existing row means');
	assertSameValue('NO', $column['Null'], 'a null would read as legacy but is better ruled out');
});

// ---------------------------------------------------------------------------

echo "\nConversion\n";

testCase('converting a legacy row leaves it readable and marks it plain', function () use (
	$databaseConnection, $postTable, $read, &$nextNo
) {
	$nextNo++;
	$databaseConnection->execute("
		INSERT INTO {$postTable} (no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host)
		VALUES (?, 1, 't1', 0, '2025-01-01 00:00:00', 'pw', 'now', 'Anon', '', 'sub &amp; more', '&gt;&gt;1<br>hi &amp; bye', '127.0.0.1')
	", [$nextNo]);

	$postUid = (int)$databaseConnection->lastInsertId();
	$before = $read($postUid);

	$databaseConnection->execute(
		"UPDATE {$postTable} SET com = ?, sub = ?, text_format = ? WHERE post_uid = ?",
		[
			legacyTextConverter::comment($before->getComment()),
			legacyTextConverter::field($before->getSubject()),
			textFormat::PLAIN_TEXT->value,
			$postUid,
		]
	);

	$after = $read($postUid);

	assertSameValue(textFormat::PLAIN_TEXT, $after->getTextFormat(), 'the row should now be plain text');
	assertSameValue(">>1\nhi & bye", $after->getComment(), 'the comment should be the text the poster typed');
	assertSameValue('sub & more', $after->getSubject(), 'the subject should be the text the poster typed');
});

// ---------------------------------------------------------------------------

echo "\n";

if ($failed) {
	echo "\033[31m" . count($failed) . " failed\033[0m, {$passed} passed\n\n";
	foreach ($failed as $failure) {
		echo "  - {$failure}\n";
	}
	exit(1);
}

echo "\033[32m{$passed} passed\033[0m\n";
exit(0);
