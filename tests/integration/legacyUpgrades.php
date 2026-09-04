<?php

/**
 * Integration test for the upgrade migrations that import an older install's data.
 *
 * The importers behind them (legacy bans, blotter, board config files, HTML posts) read files
 * and rewrite rows, so what needs an engine is the whole path: `up` on a schema seeded with the
 * old shapes leaves the new ones behind, and a second `up` finds nothing left to do.
 *
 * The migration runner is pointed at a scratch app root whose migrations/ and module/ are links
 * back into the checkout and whose global/ holds the legacy files.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/legacyUpgrades.php
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
// The config schema and global config are found through paths.php; nothing here reads
// databaseSettings.php.
require_once $root . '/paths.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\post\textFormat;

use const Kokonotsuba\GLOBAL_BOARD_UID;

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

$dsn = getenv('KOKO_TEST_DSN');
$user = getenv('KOKO_TEST_USER');
$pass = getenv('KOKO_TEST_PASS');

if ($dsn === false || $dsn === '') {
	fwrite(STDERR, "KOKO_TEST_DSN is not set; skipping.\n");
	exit(2);
}

try {
	$pdo = new PDO($dsn, $user === false ? '' : $user, $pass === false ? '' : $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "could not connect: {$e->getMessage()}\n");
	exit(2);
}

preg_match('/host=([^;]+)/', $dsn, $hostMatch);
preg_match('/dbname=([^;]+)/', $dsn, $matches);
preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);
$databaseName = $matches[1] ?? '';

echo "\nLegacy upgrade integration tests ({$databaseName})\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$tableNames = require $root . '/tables.php';

databaseConnection::createInstance([
	'DATABASE_DRIVER' => 'mysql',
	'DATABASE_HOST' => $hostMatch[1] ?? '127.0.0.1',
	'DATABASE_NAME' => $databaseName,
	'DATABASE_CHARSET' => $charsetMatch[1] ?? 'utf8mb4',
	'DATABASE_USERNAME' => $user === false ? '' : $user,
	'DATABASE_PASSWORD' => $pass === false ? '' : $pass,
]);
$databaseConnection = databaseConnection::getInstance();

// ---------------------------------------------------------------------------
// Scratch app root: the checkout's migrations and modules, an old install's global/
// ---------------------------------------------------------------------------

$scratch = rtrim(sys_get_temp_dir(), '/') . '/koko-legacy-' . bin2hex(random_bytes(4));
mkdir($scratch . '/global/board-storages/b', 0755, true);
symlink($root . '/migrations', $scratch . '/migrations');
symlink($root . '/module', $scratch . '/module');

function removeScratch(string $dir): void {
	foreach (scandir($dir) ?: [] as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$path = $dir . '/' . $entry;
		if (is_link($path) || is_file($path)) {
			unlink($path);
		} else {
			removeScratch($path);
		}
	}
	rmdir($dir);
}

$makeRunner = static function () use ($databaseConnection, $tableNames, $databaseName, $scratch): migrationRunner {
	return new migrationRunner(
		$databaseConnection,
		new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']),
		new schemaInspector($databaseConnection, $databaseName),
		$tableNames,
		$scratch,
		Kokonotsuba\KOKO_VERSION,
		static function (): void {}
	);
};

// Everything up to the import migrations, so the old shapes can be seeded first.
$makeRunner()->up('20260903_999999');

$now = time();

// An old install's boards table still records each board's config file, and install.php named the
// first board's after a random uid rather than the board's own.
$pdo->exec("ALTER TABLE `{$tableNames['BOARD_TABLE']}` ADD COLUMN config_name TEXT NOT NULL");
$pdo->exec(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}`
	 (board_uid, board_identifier, board_title, board_sub_title, storage_directory_name, config_name)
	 VALUES (1, 'b', 'Random', '', 'b', 'board-da59e91f.php'),
	        (2, 'c', 'Creative', '', 'c', 'board-2.php')"
);

// A bare post is enough for the converter; the thread it would hang off is not what is under test.
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec(
	"INSERT INTO `{$tableNames['POST_TABLE']}`
	 (no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host, text_format)
	 VALUES (1, 1, 't1', 1, NOW(), '', '', 'Anonymous', '', '', 'Hello &lt;3<br />world', '127.0.0.1', "
		. textFormat::LEGACY_HTML->value . ")"
);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

file_put_contents($scratch . '/global/globalBoardConfig.php', "<?php\n\$config['ALWAYS_NOKO'] = true;\n");
mkdir($scratch . '/global/board-configs');
file_put_contents($scratch . '/global/board-configs/board-da59e91f.php',
	"<?php\nrequire __DIR__.'/../globalBoardConfig.php';\n\$config['REPLIES_PER_PAGE'] = 50;\n");
file_put_contents($scratch . '/global/board-configs/board-2.php',
	"<?php\nrequire __DIR__.'/../globalBoardConfig.php';\n\$config['REPLIES_PER_PAGE'] = 75;\n");
file_put_contents($scratch . '/global/globalbans.log', "5.6.7.8,100,100,warning only\n");
file_put_contents($scratch . '/global/board-storages/b/bans.log.txt',
	"1.2.3.4,{$now}," . ($now + 86400) . ",spam&#44; again\n"
	. "9.9.9.9,100,200,long expired\n"
);
file_put_contents($scratch . '/global/blotter.txt', "2020/01/02<>First entry<>a\n2020-03-04 05:06:07<>Second entry\n");

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "\nRunning the imports\n";

testCase('up applies the import migrations', function () use ($makeRunner) {
	$applied = array_map(static fn ($m): string => $m->name, $makeRunner()->up());

	assertSameValue(
		['legacy_board_configs', 'legacy_bans', 'legacy_banners', 'legacy_blotter', 'legacy_post_text'],
		$applied,
		'unexpected set of migrations applied'
	);
});

testCase('the legacy config file becomes the global override row', function () use ($pdo, $tableNames) {
	$json = $pdo->query(
		"SELECT conf_values FROM `{$tableNames['BOARD_CONFIG_TABLE']}` WHERE board_uid = " . GLOBAL_BOARD_UID
	)->fetchColumn();

	assertTrueValue(is_string($json), 'no global config row was written');
	assertSameValue(true, json_decode($json, true)['ALWAYS_NOKO'] ?? null, 'ALWAYS_NOKO override missing');
});

testCase('board config files are matched through boards.config_name as well as by name', function () use ($pdo, $tableNames) {
	$rows = $pdo->query(
		"SELECT board_uid, conf_values FROM `{$tableNames['BOARD_CONFIG_TABLE']}` WHERE board_uid > 0 ORDER BY board_uid"
	)->fetchAll(PDO::FETCH_KEY_PAIR);

	assertSameValue(50, json_decode($rows[1] ?? '{}', true)['REPLIES_PER_PAGE'] ?? null, 'the randomly named first board file was not imported');
	assertSameValue(75, json_decode($rows[2] ?? '{}', true)['REPLIES_PER_PAGE'] ?? null, 'the board-{uid}.php file was not imported');
	assertSameValue(true, !array_key_exists('ALWAYS_NOKO', json_decode($rows[1], true)), 'an inherited value was frozen into the board row');
});

testCase('live and warning bans are imported, expired ones left behind', function () use ($pdo, $tableNames) {
	$rows = $pdo->query(
		"SELECT board_uid, ip_pattern, is_warning, reason FROM `{$tableNames['BAN_TABLE']}` ORDER BY ip_pattern"
	)->fetchAll(PDO::FETCH_ASSOC);

	assertSameValue(2, count($rows), 'expected the live ban and the warning only');
	assertSameValue('1.2.3.4', $rows[0]['ip_pattern'], 'board ban pattern');
	assertSameValue(1, (int)$rows[0]['board_uid'], 'board ban filed on its board');
	assertSameValue('spam, again', $rows[0]['reason'], 'encoded comma restored');
	assertSameValue(GLOBAL_BOARD_UID, (int)$rows[1]['board_uid'], 'global warning filed on the global scope');
	assertSameValue(1, (int)$rows[1]['is_warning'], 'start == expiry came across as a warning');
});

testCase('blotter lines become rows', function () use ($pdo, $tableNames) {
	$rows = $pdo->query(
		"SELECT blotter_content, date_added FROM `{$tableNames['BLOTTER_TABLE']}` ORDER BY date_added"
	)->fetchAll(PDO::FETCH_ASSOC);

	assertSameValue(2, count($rows), 'expected two blotter rows');
	assertSameValue('First entry', $rows[0]['blotter_content'], 'first entry text');
	assertSameValue('2020-01-02 00:00:00', $rows[0]['date_added'], 'date-only line gets midnight');
});

testCase('the HTML post is converted to plain text', function () use ($pdo, $tableNames) {
	$row = $pdo->query("SELECT com, text_format FROM `{$tableNames['POST_TABLE']}` WHERE no = 1")->fetch(PDO::FETCH_ASSOC);

	assertSameValue(textFormat::PLAIN_TEXT->value, (int)$row['text_format'], 'post was not flagged plain text');
	assertSameValue("Hello <3\nworld", $row['com'], 'markup was not unwound');
});

testCase('a second up finds nothing left to do', function () use ($makeRunner, $pdo, $tableNames) {
	assertSameValue([], $makeRunner()->up(), 'a second up() applied something');
	assertSameValue(2, (int)$pdo->query("SELECT COUNT(*) FROM `{$tableNames['BAN_TABLE']}`")->fetchColumn(), 'bans duplicated');
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

removeScratch($scratch);

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
