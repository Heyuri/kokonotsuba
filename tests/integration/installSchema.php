<?php

/**
 * Integration test for install.php's schema and provisioning flow, run against a real MariaDB.
 *
 * install.php owns the shipped schema: it is the only place that CREATEs the application's tables,
 * and it runs exactly once, on a machine nobody is watching. A statement that the server rejects,
 * or a provisioning step that leaves the first board half-wired, shows up as a broken install
 * rather than as a failing page - so the DDL is executed here, verbatim, the way the installer
 * executes it.
 *
 * What is pinned:
 *   - every logical table the installer declares is actually created;
 *   - the DDL applies to a real server (generated columns, FULLTEXT, foreign keys, key lengths);
 *   - re-running createTables() on a populated database is a no-op (IF NOT EXISTS);
 *   - createGlobalBoard() / addFirstBoard() / addAdminAccount() / anyAccountExists() behave.
 *
 * Usage - point this at a throwaway database of its own; it drops and recreates its tables on every
 * run. It needs a database no other kokonotsuba schema lives in: the installer's foreign keys are
 * named literally (fk_dp_post, fk_boardUID, …) and constraint names are unique per *schema*, not
 * per table, so a second copy of the schema in the same database cannot be created.
 *
 *   CREATE DATABASE koko_test_install CHARACTER SET utf8mb4;
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test_install;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/installSchema.php
 *
 * Exit code is 0 when everything passes, 1 on failure, 2 when no database is reachable.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

require __DIR__ . '/../bootstrap.php';

use Koko\Tests\Framework\InstallerHarness;

$dsn = getenv('KOKO_TEST_DSN') ?: '';
$user = getenv('KOKO_TEST_USER') ?: '';
$pass = getenv('KOKO_TEST_PASS');

if ($dsn === '') {
	fwrite(STDERR, "KOKO_TEST_DSN is not set - skipping (see the header of this file).\n");
	exit(2);
}

try {
	$pdo = new PDO($dsn, $user, $pass === false ? '' : $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "Could not connect: {$e->getMessage()}\n");
	exit(2);
}

// Test tables are prefixed so the script can never collide with a real install sharing the schema.
const PREFIX = 'instest_';

$passed = 0;
$failed = 0;

function check(string $what, bool $ok, string $detail = ''): void {
	global $passed, $failed;
	if ($ok) {
		$passed++;
		echo "  ok   $what\n";
		return;
	}
	$failed++;
	echo "  FAIL $what" . ($detail !== '' ? " - $detail" : '') . "\n";
}

/** Logical key => prefixed physical name, for every table the installer declares. */
function testTableMap(): array {
	$map = [];
	foreach (array_keys(InstallerHarness::installerTableKeys()) as $logicalKey) {
		$map[$logicalKey] = PREFIX . strtolower($logicalKey);
	}
	return $map;
}

function dropAll(PDO $pdo, array $tables): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach ($tables as $name) {
		$pdo->exec("DROP TABLE IF EXISTS `$name`");
	}
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function tableExists(PDO $pdo, string $name): bool {
	$stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
	$stmt->execute([':t' => $name]);
	return (int)$stmt->fetchColumn() === 1;
}

InstallerHarness::load();
$tableCreatorClass = InstallerHarness::cls('tableCreator');
$boardTableClass = InstallerHarness::cls('boardTable');
$accountTableClass = InstallerHarness::cls('accountTable');

$tables = testTableMap();
dropAll($pdo, $tables);

echo "install.php schema\n";

// ---- The DDL applies to a real server ---------------------------------------

$creator = new $tableCreatorClass($pdo);
try {
	$creator->createTables($tables);
	check('createTables() runs without error', true);
} catch (Throwable $e) {
	check('createTables() runs without error', false, $e->getMessage());
}

// ---- Every declared table is actually created -------------------------------

$declared = array_keys(InstallerHarness::installerTableKeys());
$missing = [];
foreach ($declared as $logicalKey) {
	if (!tableExists($pdo, $tables[$logicalKey])) {
		$missing[] = $logicalKey;
	}
}
check(
	'every table the installer declares exists after install',
	$missing === [],
	'never created: ' . implode(', ', $missing)
);

// ---- Re-running is a no-op --------------------------------------------------

try {
	$creator->createTables($tables);
	check('createTables() is idempotent', true);
} catch (Throwable $e) {
	check('createTables() is idempotent', false, $e->getMessage());
}

// ---- Provisioning flow ------------------------------------------------------

echo "provisioning\n";

$boardTable = new $boardTableClass($pdo, $tables['BOARD_TABLE'], $pdo->query('SELECT DATABASE()')->fetchColumn());
$accountTable = new $accountTableClass($pdo, $tables['ACCOUNT_TABLE']);

check('a fresh install has no accounts', $accountTable->anyAccountExists() === false);

try {
	$created = $boardTable->createGlobalBoard();
	check('createGlobalBoard() inserts the reserved board', $created === true);
	check('createGlobalBoard() is idempotent', $boardTable->createGlobalBoard() === false);
} catch (Throwable $e) {
	check('createGlobalBoard() inserts the reserved board', false, $e->getMessage());
}

$globalUid = Kokonotsuba\GLOBAL_BOARD_UID;
$stmt = $pdo->prepare("SELECT board_identifier, listed FROM `{$tables['BOARD_TABLE']}` WHERE board_uid = :uid");
$stmt->execute([':uid' => $globalUid]);
$globalRow = $stmt->fetch();
check('the global board is stored unlisted', $globalRow !== false && (int)$globalRow['listed'] === 0);

// createBoardAndFiles() derives the storage directory name from getLastBoardUID() + 1 *before*
// inserting the board, so this pins that the name it picks matches the UID the board ends up with.
$predictedUid = $boardTable->getLastBoardUID() + 1;
$boardTable->addFirstBoard('b', 'board@example.net', 'an example board', 'storage-' . $predictedUid);
$actualUid = $boardTable->getLastBoardUID();
check(
	'the storage directory name matches the first board UID',
	(int)$actualUid === (int)$predictedUid,
	"storage-$predictedUid was created for board_uid $actualUid"
);

try {
	$added = $accountTable->addAdminAccount('admin', 'hunter2', Kokonotsuba\userRole::LEV_ADMIN->value);
	check('addAdminAccount() inserts the admin', $added === true);
} catch (Throwable $e) {
	check('addAdminAccount() inserts the admin', false, $e->getMessage());
}

$row = $pdo->query("SELECT username, role, password_hash FROM `{$tables['ACCOUNT_TABLE']}`")->fetch();
check('the admin is stored with LEV_ADMIN', $row !== false && (int)$row['role'] === Kokonotsuba\userRole::LEV_ADMIN->value);
check('the password is stored hashed', $row !== false && password_verify('hunter2', $row['password_hash']));

check('anyAccountExists() sees the provisioned admin', $accountTable->anyAccountExists() === true);

// The installer refuses to re-provision when accounts exist; the column is declared UNIQUE, so a
// second account with the same username must be rejected by the database as well.
try {
	$accountTable->addAdminAccount('admin', 'hunter2', Kokonotsuba\userRole::LEV_ADMIN->value);
	check('a duplicate username is rejected', false, 'the insert succeeded');
} catch (PDOException $e) {
	check('a duplicate username is rejected', true);
}

// ---- Referential integrity the app relies on --------------------------------

echo "constraints\n";

$stmt = $pdo->prepare("INSERT INTO `{$tables['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('t1', 1, 1, :uid)");
try {
	$stmt->execute([':uid' => 999999]);
	check('a thread cannot reference a missing board', false, 'the insert succeeded');
} catch (PDOException $e) {
	check('a thread cannot reference a missing board', true);
}

$pdo->prepare("INSERT INTO `{$tables['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('t1', 1, 1, :uid)")
	->execute([':uid' => $actualUid]);
$pdo->prepare(
	"INSERT INTO `{$tables['POST_TABLE']}` (no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host)
	 VALUES (1, :uid, 't1', 1, NOW(), '', '', '', '', '', '', '127.0.0.1')"
)->execute([':uid' => $actualUid]);
$postUid = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO `{$tables['DELETED_POSTS_TABLE']}` (post_uid) VALUES (:p)")->execute([':p' => $postUid]);
try {
	$pdo->prepare("INSERT INTO `{$tables['DELETED_POSTS_TABLE']}` (post_uid) VALUES (:p)")->execute([':p' => $postUid]);
	check('a post cannot be deleted twice while open', false, 'the second delete row was accepted');
} catch (PDOException $e) {
	check('a post cannot be deleted twice while open', true);
}

$pdo->prepare("DELETE FROM `{$tables['BOARD_TABLE']}` WHERE board_uid = :uid")->execute([':uid' => $actualUid]);
$remainingPosts = (int)$pdo->query("SELECT COUNT(*) FROM `{$tables['POST_TABLE']}`")->fetchColumn();
check('deleting a board cascades to its posts', $remainingPosts === 0, "$remainingPosts posts left behind");

dropAll($pdo, $tables);

// ---- Character set ----------------------------------------------------------
//
// No statement in the DDL names a CHARACTER SET, so every table inherits the database default.
// databaseSettings.php carries a DATABASE_CHARSET that the installer applies to the *connection*
// only, and the README's setup step is a bare `CREATE DATABASE kokonotsuba;` - so on a server
// whose default is not utf8mb4, the install silently produces a schema that cannot store the
// multibyte text an imageboard is mostly made of.
//
// This runs in its own database with a deliberately non-utf8mb4 default; it is skipped where the
// test user may not create one.

echo "character set\n";

$charsetDb = 'koko_test_charset';
try {
	$pdo->exec("DROP DATABASE IF EXISTS `$charsetDb`");
	$pdo->exec("CREATE DATABASE `$charsetDb` CHARACTER SET latin1");
} catch (PDOException $e) {
	echo "  skip the test user cannot create a scratch database ({$e->getMessage()})\n";
	$charsetDb = null;
}

if ($charsetDb !== null) {
	$scratchDsn = preg_replace('/dbname=[^;]*/', "dbname=$charsetDb", $dsn);
	$scratch = new PDO($scratchDsn, $user, $pass === false ? '' : $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);

	(new $tableCreatorClass($scratch))->createTables($tables);

	$stmt = $scratch->prepare(
		'SELECT COLUMN_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND CHARACTER_SET_NAME IS NOT NULL AND CHARACTER_SET_NAME <> :cs'
	);
	$stmt->execute([':db' => $charsetDb, ':t' => $tables['POST_TABLE'], ':cs' => 'utf8mb4']);
	$wrongCharset = $stmt->fetchAll(PDO::FETCH_COLUMN);

	check(
		'post columns are utf8mb4 whatever the database default is',
		$wrongCharset === [],
		count($wrongCharset) . ' column(s) inherited the database charset instead: ' . implode(', ', array_slice($wrongCharset, 0, 5))
	);

	$scratch->prepare("INSERT INTO `{$tables['BOARD_TABLE']}` (board_title, storage_directory_name) VALUES ('t', 's')")->execute();
	$scratchBoard = (int)$scratch->lastInsertId();
	$scratch->prepare("INSERT INTO `{$tables['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('t1', 1, 1, :u)")
		->execute([':u' => $scratchBoard]);

	try {
		$scratch->prepare(
			"INSERT INTO `{$tables['POST_TABLE']}` (no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host)
			 VALUES (1, :u, 't1', 1, NOW(), '', '', '', '', '', :com, '127.0.0.1')"
		)->execute([':u' => $scratchBoard, ':com' => 'こんにちは 🍜']);
		check('a multibyte post survives the install', true);
	} catch (PDOException $e) {
		check('a multibyte post survives the install', false, $e->getMessage());
	}

	$scratch = null;
	$pdo->exec("DROP DATABASE `$charsetDb`");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
