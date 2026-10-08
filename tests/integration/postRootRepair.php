<?php

/**
 * The upgrade path for a `root` column that auto-updated: the post times it overwrote come back
 * from `now`, the auto-update goes, and the counter history is backfilled.
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test_rootrepair;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/postRootRepair.php
 *
 * Drops every table in the database it is pointed at.
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

$dsn = getenv('KOKO_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=koko_test_rootrepair;charset=utf8mb4';
$user = getenv('KOKO_TEST_USER') ?: 'koko_test';
$pass = getenv('KOKO_TEST_PASS') ?: 'kokotest';

preg_match('/host=([^;]+)/', $dsn, $hostMatch);
preg_match('/dbname=([^;]+)/', $dsn, $nameMatch);
$databaseName = $nameMatch[1] ?? '';

try {
	$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
	exit(2);
}

databaseConnection::createInstance([
	'DATABASE_DRIVER' => 'mysql',
	'DATABASE_HOST' => $hostMatch[1] ?? '127.0.0.1',
	'DATABASE_NAME' => $databaseName,
	'DATABASE_CHARSET' => 'utf8mb4',
	'DATABASE_USERNAME' => $user,
	'DATABASE_PASSWORD' => $pass,
]);
$connection = databaseConnection::getInstance();
$tables = require $root . '/tables.php';
$posts = $tables['POST_TABLE'];
$history = $tables['POST_NUMBER_HISTORY_TABLE'];

$runner = static fn(): migrationRunner => new migrationRunner(
	$connection,
	new migrationLedger($connection, $tables['SCHEMA_MIGRATION_TABLE']),
	new schemaInspector($connection, $databaseName),
	$tables,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (string $message, string $level): void {}
);

$failures = 0;
$check = static function (bool $ok, string $message) use (&$failures): void {
	echo ($ok ? '  ok   ' : '  FAIL ') . $message . "\n";
	$failures += $ok ? 0 : 1;
};
$rootExtra = static fn(): string => (string)$pdo->query(
	"SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$posts}' AND COLUMN_NAME = 'root'"
)->fetchColumn();

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec("DROP TABLE `{$table}`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$runner()->up();
$check(stripos($rootExtra(), 'on update') === false, 'a fresh install has no auto-updating root');

// Age the install: the column auto-updates and the new migrations have not run.
$pdo->exec("ALTER TABLE `{$posts}` MODIFY `root` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
$pdo->exec("DELETE FROM `{$tables['SCHEMA_MIGRATION_TABLE']}` WHERE version LIKE '20261008%'");

$pdo->exec("INSERT INTO `{$tables['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name, listed) VALUES (1, 'a', 'A', 'a', 1)");
$pdo->exec("INSERT INTO `{$tables['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('t', 1, 1, 1)");

// Board time is UTC+10, as the vichan import in the benchmark set.
$times = [1 => '2026-09-01 12:00:00', 2 => '2026-09-02 12:00:00', 3 => '2026-09-03 20:00:00', 4 => '2026-09-04 12:00:00', 5 => '2026-09-10 01:00:00', 6 => '2026-09-10 02:00:00'];
$insert = $pdo->prepare(
	"INSERT INTO `{$posts}` (post_uid, no, boardUID, thread_uid, post_position, is_op, root, pwd, now, name, email, sub, com, host, status)
	 VALUES (?, ?, 1, 't', ?, ?, ?, '', ?, '', '', '', 'x', '127.0.0.1', '')"
);
foreach ($times as $no => $time) {
	$local = gmdate('Y/m/d', strtotime("{$time} UTC") + 36000) . '(Mon)' . gmdate('H:i:s', strtotime("{$time} UTC") + 36000);
	$now = '<span class="postDate">' . substr($local, 0, 10) . '</span><span class="postDay">(Mon)</span><span class="postTime">' . substr($local, -8) . '</span>';
	$insert->execute([$no, $no, $no - 1, $no === 1 ? 1 : 0, $time, $now]);
}

// The legacy conversion writes every row; three of them get clobbered.
$pdo->exec("UPDATE `{$posts}` SET com = 'converted' WHERE no <= 3");
$check((int)$pdo->query("SELECT COUNT(*) FROM `{$posts}` WHERE DATE(root) = CURDATE()")->fetchColumn() === 3, 'the aged column moved three post times');

$runner()->up();

$after = $pdo->query("SELECT no, root FROM `{$posts}` ORDER BY no")->fetchAll(PDO::FETCH_KEY_PAIR);
$check(array_map('strval', $after) === $times, 'every post time is back where it was');
$check(stripos($rootExtra(), 'on update') === false, 'root no longer auto-updates');

$readings = $pdo->query("SELECT day, post_number FROM `{$history}` WHERE board_uid = 1 ORDER BY day")->fetchAll(PDO::FETCH_KEY_PAIR);
$check(
	array_map('intval', $readings) === ['2026-09-01' => 1, '2026-09-02' => 2, '2026-09-03' => 3, '2026-09-04' => 4, '2026-09-10' => 6],
	'history is backfilled from the surviving posts'
);

$pdo->exec("UPDATE `{$posts}` SET com = 'edited' WHERE no = 1");
$check((string)$pdo->query("SELECT root FROM `{$posts}` WHERE no = 1")->fetchColumn() === $times[1], 'an edit leaves the post time alone');

$check($runner()->up() === [], 'running up again does nothing');

echo $failures ? "\n{$failures} failure(s)\n" : "\nall passed\n";
exit($failures ? 1 : 0);
