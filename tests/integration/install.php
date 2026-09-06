<?php

/**
 * Integration test for the installer.
 *
 * Runs Kokonotsuba\install\installer against a scratch app root and a throwaway database, the
 * same way install.php does, and checks what it leaves behind: the config files, the board
 * directory and its rows, the admin account, the marker. Then the two things it must get right
 * when something goes wrong: refusing to install over a live database, and rolling back a
 * failure inside the board transaction so the next attempt starts clean.
 *
 * The scratch root links migrations/, module/ and static/ back into the checkout; everything
 * the installer writes lands in the scratch directory and is removed at the end.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/install.php
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
require_once $root . '/code/Puchiko/includes.php';

use Kokonotsuba\install\installDefaults;
use Kokonotsuba\install\installer;
use Kokonotsuba\install\installInput;
use Kokonotsuba\install\installResult;
use Kokonotsuba\install\installStep;
use Kokonotsuba\userRole;

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

/** The label and status of every step, for a failure message that says where it stopped. */
function describeSteps(installResult $result): string {
	return implode("\n", array_map(
		static fn (installStep $step): string => "  [{$step->status}] {$step->label}: {$step->detail}",
		$result->steps()
	));
}

function assertSucceeded(installResult $result): void {
	assertTrueValue($result->succeeded(), "install failed:\n" . describeSteps($result));
}

function assertFailedAt(installResult $result, string $label): void {
	assertTrueValue(!$result->succeeded(), "install succeeded but was expected to fail at '{$label}':\n" . describeSteps($result));
	assertSameValue($label, $result->failure()?->label, "install stopped at the wrong step:\n" . describeSteps($result));
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
preg_match('/dbname=([^;]+)/', $dsn, $nameMatch);
$databaseHost = $hostMatch[1] ?? '127.0.0.1';
$databaseName = $nameMatch[1] ?? '';

$dropAll = static function () use ($pdo): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
		$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	}
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$countRows = static fn (string $table, string $where = ''): int => (int)$pdo
	->query("SELECT COUNT(*) FROM `{$table}`" . ($where !== '' ? " WHERE {$where}" : ''))
	->fetchColumn();

echo "\nInstaller integration tests ({$databaseName})\n";

$dropAll();

$tableNames = require $root . '/tables.php';

// ---------------------------------------------------------------------------
// Scratch app root
// ---------------------------------------------------------------------------

$scratch = rtrim(sys_get_temp_dir(), '/') . '/koko-install-' . bin2hex(random_bytes(4));
mkdir($scratch . '/global/board-storages', 0755, true);
symlink($root . '/migrations', $scratch . '/migrations');
symlink($root . '/module', $scratch . '/module');
symlink($root . '/static', $scratch . '/static');

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

/** What a run leaves in the scratch root, so the next scenario starts from a fresh clone. */
$cleanScratch = static function () use ($scratch): void {
	foreach ([
		$scratch . '/databaseSettings.php',
		$scratch . '/global/siteSettings.php',
		$scratch . '/global/.installed',
	] as $file) {
		@unlink($file);
	}
	foreach ([$scratch . '/boards', $scratch . '/global/board-storages/storage-1'] as $dir) {
		if (is_dir($dir)) {
			removeScratch($dir);
		}
	}
};

$form = [
	'db_host' => $databaseHost,
	'db_port' => '3306',
	'db_name' => $databaseName,
	'db_user' => $user === false ? '' : $user,
	'db_password' => $pass === false ? '' : $pass,
	'admin_username' => 'admin',
	'admin_password' => 'correct horse battery',
	'admin_password_confirm' => 'correct horse battery',
	'board_identifier' => 'b',
	'board_title' => 'Test <b>board</b>',
	'board_sub_title' => 'an example board',
	'website_url' => 'https://example.net/koko/boards/',
	'home_url' => 'https://example.net/',
	'static_url' => 'https://example.net/koko/static/',
	'static_path' => $scratch . '/static/',
];

$defaults = installDefaults::detect(['HTTP_HOST' => 'example.net', 'SCRIPT_NAME' => '/koko/install.php'], $scratch);

$runInstaller = static function (array $changes = []) use ($form, $scratch, $tableNames, $defaults): installResult {
	$input = installInput::fromArray(array_merge($form, $changes));
	assertTrueValue($input->isValid(), 'form input did not validate: ' . var_export($input->errors(), true));

	return (new installer($scratch, $tableNames, Kokonotsuba\KOKO_VERSION, $defaults))->run($input);
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

try {

echo "\nA clean install\n";

$result = null;

testCase('the install succeeds on an empty database', function () use ($runInstaller, &$result) {
	$result = $runInstaller();
	assertSucceeded($result);
});

testCase('the schema was migrated to the head', function () use ($result, $countRows, $tableNames, $root) {
	$migrationFiles = count(glob($root . '/migrations/*.php') ?: []);
	assertTrueValue($countRows($tableNames['SCHEMA_MIGRATION_TABLE']) >= $migrationFiles, 'not every core migration was recorded');
	assertTrueValue($countRows($tableNames['POST_TABLE']) === 0, 'the posts table should exist and be empty');
});

testCase('the config files are written with the secrets generated', function () use ($scratch, $databaseName) {
	$database = include $scratch . '/databaseSettings.php';
	assertSameValue($databaseName, $database['DATABASE_NAME'], 'DATABASE_NAME');
	assertSameValue(64, strlen((string)$database['ANON_IP_SALT']), 'ANON_IP_SALT should be a fresh 64-hex secret');
	assertSameValue(0640, fileperms($scratch . '/databaseSettings.php') & 0777, 'databaseSettings.php mode');

	$site = include $scratch . '/global/siteSettings.php';
	assertSameValue('https://example.net/koko/boards/', $site['WEBSITE_URL'], 'WEBSITE_URL');
	assertSameValue($scratch . '/static/', $site['STATIC_PATH'], 'STATIC_PATH');
	assertSameValue(64, strlen((string)$site['TRIPSALT']), 'TRIPSALT should be a fresh 64-hex secret');
	assertSameValue(64, strlen((string)$site['IDSEED']), 'IDSEED should be a fresh 64-hex secret');
	assertTrueValue($site['TRIPSALT'] !== $site['IDSEED'], 'TRIPSALT and IDSEED must differ');
});

testCase('no temporary or backup config file is left behind', function () use ($scratch) {
	$leftovers = array_merge(
		glob($scratch . '/{,.}*.tmp-*', GLOB_BRACE) ?: [],
		glob($scratch . '/{,.}*.bak-*', GLOB_BRACE) ?: [],
		glob($scratch . '/global/{,.}*.tmp-*', GLOB_BRACE) ?: [],
		glob($scratch . '/global/{,.}*.bak-*', GLOB_BRACE) ?: []
	);
	assertSameValue([], $leftovers, 'sidecar files left in place');
});

testCase('the board directory has its entry point, UID file and upload directory', function () use ($scratch) {
	$boardDir = $scratch . '/boards/b/';
	assertTrueValue(is_dir($boardDir), 'boards/b/ missing');
	assertTrueValue(is_dir($boardDir . 'src'), 'boards/b/src/ missing');
	assertSameValue("board_uid = 1\n", file_get_contents($boardDir . 'boardUID.ini'), 'boardUID.ini');

	$entry = (string)file_get_contents($boardDir . 'koko.php');
	assertTrueValue(str_starts_with($entry, '<?php require_once '), 'koko.php should require the backend');
	assertTrueValue(str_contains($entry, var_export($scratch . '/koko.php', true)), 'koko.php should point at the app root');
	assertTrueValue(!is_file($boardDir . 'index.html'), 'no placeholder index.html should be written');
});

testCase('the storage directory lives under the app root, named after the UID', function () use ($scratch, $pdo, $tableNames) {
	assertTrueValue(is_dir($scratch . '/global/board-storages/storage-1'), 'storage-1 missing');
	$stored = $pdo->query("SELECT storage_directory_name FROM `{$tableNames['BOARD_TABLE']}` WHERE board_uid = 1")->fetchColumn();
	assertSameValue('storage-1', $stored, 'storage_directory_name');
});

testCase('the board row is sanitised the way the admin panel does it', function () use ($pdo, $tableNames) {
	$row = $pdo->query("SELECT * FROM `{$tableNames['BOARD_TABLE']}` WHERE board_uid = 1")->fetch(PDO::FETCH_ASSOC);
	assertSameValue('b', $row['board_identifier'], 'identifier');
	assertSameValue('Test &lt;b&gt;board&lt;/b&gt;', $row['board_title'], 'title should be HTML-escaped');
	assertSameValue('1', (string)$row['listed'], 'the first board is listed');
});

testCase('the path cache points at the board directory', function () use ($pdo, $tableNames, $scratch) {
	$path = $pdo->query("SELECT board_path FROM `{$tableNames['BOARD_PATH_CACHE_TABLE']}` WHERE boardUID = 1")->fetchColumn();
	assertSameValue($scratch . '/boards/b/', $path, 'board_path');
});

testCase('the admin account is created with the Admin role and a verifiable hash', function () use ($pdo, $tableNames) {
	$rows = $pdo->query("SELECT username, role, password_hash FROM `{$tableNames['ACCOUNT_TABLE']}`")->fetchAll(PDO::FETCH_ASSOC);
	assertSameValue(1, count($rows), 'exactly one account');
	assertSameValue('admin', $rows[0]['username'], 'username');
	assertSameValue(userRole::LEV_ADMIN->value, (int)$rows[0]['role'], 'role');
	assertTrueValue(password_verify('correct horse battery', $rows[0]['password_hash']), 'password_hash does not verify');
});

testCase('the marker locks the installer and the result names the board', function () use ($scratch, $result) {
	assertTrueValue(installer::isInstalled($scratch), 'isInstalled() should be true');
	assertSameValue('https://example.net/koko/boards/b/koko.php', $result->boardUrl(), 'board URL');
	assertTrueValue($result->followUpCommands() !== [], 'follow-up commands should be offered');
});

echo "\nA second install over a live database\n";

testCase('is refused before anything is written', function () use ($runInstaller, $scratch, $countRows, $tableNames) {
	@unlink($scratch . '/global/.installed');
	$before = filemtime($scratch . '/databaseSettings.php');

	$result = $runInstaller(['admin_username' => 'intruder']);
	assertFailedAt($result, 'Existing install');

	assertSameValue(1, $countRows($tableNames['ACCOUNT_TABLE']), 'no second account');
	assertSameValue($before, filemtime($scratch . '/databaseSettings.php'), 'databaseSettings.php should be untouched');
});

echo "\nA failure inside the board transaction\n";

$dropAll();
$cleanScratch();

// A directory where the board's koko.php has to go makes writeBoardFiles() throw after the
// board row has been inserted, which is the point of no return the rollback has to cover.
mkdir($scratch . '/boards/b/koko.php', 0755, true);

testCase('is reported as a rollback', function () use ($runInstaller) {
	$result = $runInstaller();
	assertFailedAt($result, 'Install rolled back');
});

testCase('leaves no rows, no config files and no marker', function () use ($scratch, $countRows, $tableNames) {
	assertSameValue(0, $countRows($tableNames['BOARD_TABLE'], 'board_uid > 0'), 'board rows');
	assertSameValue(0, $countRows($tableNames['ACCOUNT_TABLE']), 'account rows');
	assertSameValue(0, $countRows($tableNames['BOARD_PATH_CACHE_TABLE']), 'path cache rows');
	assertTrueValue(!file_exists($scratch . '/databaseSettings.php'), 'databaseSettings.php should be removed');
	assertTrueValue(!file_exists($scratch . '/global/siteSettings.php'), 'siteSettings.php should be removed');
	assertTrueValue(!file_exists($scratch . '/global/.installed'), 'marker should not be written');
	assertTrueValue(!is_dir($scratch . '/global/board-storages/storage-1'), 'storage-1 should be removed');
	assertTrueValue(!is_dir($scratch . '/boards/b/src'), 'boards/b/src should be removed');
});

testCase('keeps the schema, so the next attempt only creates the board', function () use ($countRows, $tableNames) {
	assertTrueValue($countRows($tableNames['SCHEMA_MIGRATION_TABLE']) > 0, 'the ledger should survive the rollback');
});

echo "\nRetrying after a failure\n";

rmdir($scratch . '/boards/b/koko.php');

// Secrets that were already in place must survive a re-run: the salts are what every tripcode
// and anonymised address is derived from.
file_put_contents($scratch . '/databaseSettings.php', "<?php return ['ANON_IP_SALT' => 'keep-this-salt'];\n");
file_put_contents($scratch . '/global/siteSettings.php', "<?php return ['TRIPSALT' => 'keep-this-tripsalt', 'IDSEED' => 'keep-this-seed'];\n");

testCase('succeeds against the partially migrated database', function () use ($runInstaller, $scratch, $countRows, $tableNames) {
	$result = $runInstaller();
	assertSucceeded($result);
	assertSameValue(1, $countRows($tableNames['BOARD_TABLE'], 'board_uid > 0'), 'board rows');
	assertSameValue(1, $countRows($tableNames['ACCOUNT_TABLE']), 'account rows');
	assertTrueValue(is_file($scratch . '/boards/b/koko.php'), 'boards/b/koko.php');
	assertTrueValue(installer::isInstalled($scratch), 'marker');
});

testCase('keeps the secrets that were already configured', function () use ($scratch) {
	$database = include $scratch . '/databaseSettings.php';
	assertSameValue('keep-this-salt', $database['ANON_IP_SALT'], 'ANON_IP_SALT');

	$site = include $scratch . '/global/siteSettings.php';
	assertSameValue('keep-this-tripsalt', $site['TRIPSALT'], 'TRIPSALT');
	assertSameValue('keep-this-seed', $site['IDSEED'], 'IDSEED');
});

testCase('discards the backups of the files it replaced', function () use ($scratch) {
	$leftovers = array_merge(
		glob($scratch . '/{,.}*.bak-*', GLOB_BRACE) ?: [],
		glob($scratch . '/global/{,.}*.bak-*', GLOB_BRACE) ?: []
	);
	assertSameValue([], $leftovers, 'backup files left in place');
});

} finally {
	$dropAll();
	removeScratch($scratch);
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";
if ($failed === []) {
	echo "\033[32mAll {$passed} passed.\033[0m\n";
	exit(0);
}

echo "\033[31m" . count($failed) . " failed, {$passed} passed.\033[0m\n\n";
foreach ($failed as $failure) {
	echo "  - {$failure}\n\n";
}
exit(1);
