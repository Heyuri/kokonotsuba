<?php

/**
 * Integration tests for the threadMod module's grants and role resolution, against a real MariaDB.
 *
 * The policy is unit tested; what needs an engine is the thread_mods table (the upsert that
 * reopens a revoked grant, the owner row a transfer writes, the LIKE that counts OP deletions)
 * and threadModService reading ownership off a real opening post: by token, by password, and
 * not at all once ownership has moved.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/threadMod.php
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
require_once $root . '/module/threadMod/threadRole.php';
require_once $root . '/module/threadMod/threadModPolicy.php';
require_once $root . '/module/threadMod/threadModRepository.php';
require_once $root . '/module/threadMod/threadModService.php';

use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\Modules\threadMod\threadModPolicy;
use Kokonotsuba\Modules\threadMod\threadModRepository;
use Kokonotsuba\Modules\threadMod\threadModService;
use Kokonotsuba\Modules\threadMod\threadRole;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\request\request;
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
// Fixtures: one board, one thread, an OP with a token and a password, three replies
// ---------------------------------------------------------------------------

const OP_HASH = 'aaaaaaaaaaaaaaaa';
const MOD_HASH = 'bbbbbbbbbbbbbbbb';
const OTHER_HASH = 'cccccccccccccccc';
const OP_PASSWORD = 'hunter2';
const THREAD = 'f00d';

$pwdHash = password_hash(OP_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
$now = date('Y-m-d H:i:s');

$pdo->exec("INSERT INTO `{$t('BOARD_TABLE')}` (board_uid, board_identifier, board_title, storage_directory_name) VALUES (1, 'test', 'Test', 'test')");
$pdo->exec("INSERT INTO `{$t('THREAD_TABLE')}` (thread_uid, post_op_number, post_op_post_uid, boardUID) VALUES ('" . THREAD . "', 1, 1, 1)");
$insert = $pdo->prepare("INSERT INTO `{$t('POST_TABLE')}`
	(post_uid, no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host, visitor_token_hash, status, capcode)
	VALUES (?, ?, 1, '" . THREAD . "', ?, '{$now}', ?, '', 'Anonymous', '', '', ?, '203.0.113.1', ?, '', ?)");
$insert->execute([1, 1, 1, $pwdHash, 'op', OP_HASH, '']);
$insert->execute([2, 2, 0, '', 'reply by future mod', MOD_HASH, '']);
$insert->execute([3, 3, 0, '', 'reply by someone', OTHER_HASH, '']);
$insert->execute([4, 4, 0, '', 'cookieless reply', '', '']);

$postRepository = new postRepository($db, $t('POST_TABLE'), $t('THREAD_TABLE'), $t('DELETED_POSTS_TABLE'), $t('FILE_TABLE'), $t('SOUDANE_TABLE'), $t('NOTE_TABLE'), $t('ACCOUNT_TABLE'), $t('COUNTRY_FLAG_TABLE'), $t('DISPLAY_IP_TABLE'), $t('REPORT_TABLE'), $t('BOARD_TABLE'));
$threadRepository = new threadRepository($db, $t('POST_TABLE'), $t('THREAD_TABLE'), $t('THREAD_THEMES_TABLE'), $t('DELETED_POSTS_TABLE'), $t('FILE_TABLE'), $t('ACCOUNT_TABLE'), $t('SOUDANE_TABLE'), $t('NOTE_TABLE'), $t('COUNTRY_FLAG_TABLE'), $t('DISPLAY_IP_TABLE'), $t('REPORT_TABLE'));
$repository = new threadModRepository($db, $t('THREAD_MOD_TABLE'), $t('POST_TABLE'), $t('DELETED_POSTS_TABLE'));

/** A service seeing the world as one browser, with the cookies it carries. */
$serviceAs = static function (string $hash, array $cookies = [], bool $passwordOwnership = true) use ($repository, $postRepository, $threadRepository): threadModService {
	return new threadModService(
		$repository,
		$postRepository,
		$threadRepository,
		fn(): string => $hash,
		new cookieService($cookies),
		new request([], [], ['REQUEST_TIME' => time()]),
		new threadModPolicy(0),
		$passwordOwnership
	);
};

echo "threadMod integration tests ({$databaseName})\n\n";

echo "Ownership\n";

testCase('the opening poster is owner by token', function () use ($serviceAs) {
	assertSameValue(threadRole::OWNER, $serviceAs(OP_HASH)->resolveRole(THREAD), 'OP token should own the thread');
});

testCase('a stranger is nobody', function () use ($serviceAs) {
	assertSameValue(threadRole::NONE, $serviceAs(OTHER_HASH)->resolveRole(THREAD), 'another token should hold no role');
	assertSameValue(threadRole::NONE, $serviceAs('')->resolveRole(THREAD), 'no token should hold no role');
});

testCase('the post password owns the thread from any browser, via cookie or typed', function () use ($serviceAs) {
	assertSameValue(threadRole::OWNER, $serviceAs(OTHER_HASH, ['pwdc' => OP_PASSWORD])->resolveRole(THREAD), 'pwdc cookie should own');
	assertSameValue(threadRole::OWNER, $serviceAs('')->resolveRole(THREAD, null, OP_PASSWORD), 'typed password should own');
	assertSameValue(threadRole::NONE, $serviceAs('')->resolveRole(THREAD, null, 'wrong'), 'wrong password should not');
});

testCase('password ownership can be switched off', function () use ($serviceAs) {
	assertSameValue(threadRole::NONE, $serviceAs(OTHER_HASH, ['pwdc' => OP_PASSWORD], false)->resolveRole(THREAD), 'password should not count when disabled');
});

echo "\nGrants\n";

testCase('a grant makes a mod, and shows in the roster and the batched lookup', function () use ($serviceAs, $repository, $postRepository) {
	$owner = $serviceAs(OP_HASH);
	$owner->grantMod(THREAD, $postRepository->getPostByUid(2), OP_HASH);

	assertSameValue(threadRole::MOD, $serviceAs(MOD_HASH)->resolveRole(THREAD), 'granted browser should be a mod');

	$roster = $repository->listActive(THREAD);
	assertSameValue(1, count($roster), 'one live grant');
	assertSameValue(2, (int)$roster[0]['granted_from_no'], 'roster shows the post it came from');

	$roles = $repository->activeRolesForToken(MOD_HASH, [THREAD, 'nope']);
	assertSameValue([THREAD => threadRole::MOD], $roles, 'batched lookup finds the grant');
});

testCase('rolesForThreads answers for owner and mod and leaves strangers out', function () use ($serviceAs) {
	assertSameValue([THREAD => threadRole::OWNER], $serviceAs(OP_HASH)->rolesForThreads([THREAD]), 'owner by token');
	assertSameValue([THREAD => threadRole::MOD], $serviceAs(MOD_HASH)->rolesForThreads([THREAD]), 'mod by row');
	assertSameValue([], $serviceAs(OTHER_HASH)->rolesForThreads([THREAD]), 'stranger');
});

testCase('revoking closes the grant and a re-grant reopens the same row', function () use ($serviceAs, $repository, $postRepository, $pdo, $t) {
	assertTrueValue($serviceAs(OP_HASH)->revokeMod(THREAD, MOD_HASH, OP_HASH, null), 'revoke should report a closed row');
	assertSameValue(threadRole::NONE, $serviceAs(MOD_HASH)->resolveRole(THREAD), 'revoked browser is nobody');
	assertTrueValue(!$serviceAs(OP_HASH)->revokeMod(THREAD, MOD_HASH, OP_HASH, null), 'second revoke finds nothing');

	$serviceAs(OP_HASH)->grantMod(THREAD, $postRepository->getPostByUid(2), OP_HASH);
	assertSameValue(threadRole::MOD, $serviceAs(MOD_HASH)->resolveRole(THREAD), 're-granted');
	assertSameValue(1, (int)$pdo->query("SELECT COUNT(*) FROM `{$t('THREAD_MOD_TABLE')}`")->fetchColumn(), 'still one row for that browser');
});

echo "\nTransfer\n";

testCase('a transfer moves ownership off the opening post', function () use ($serviceAs, $postRepository, $repository) {
	$serviceAs(OP_HASH)->transferOwnership(THREAD, $postRepository->getPostByUid(3), OP_HASH);

	assertSameValue(threadRole::OWNER, $serviceAs(OTHER_HASH)->resolveRole(THREAD), 'new owner by row');
	assertSameValue(threadRole::MOD, $serviceAs(OP_HASH)->resolveRole(THREAD), 'old owner keeps a seat as mod');
	// the password proved the opening post, and the opening post no longer says who owns the thread
	assertSameValue(threadRole::NONE, $serviceAs('', ['pwdc' => OP_PASSWORD])->resolveRole(THREAD, null, OP_PASSWORD), 'the OP password no longer counts');
	assertSameValue(threadRole::MOD, $serviceAs(MOD_HASH)->resolveRole(THREAD), 'existing mod untouched');
	assertSameValue([THREAD => true], $repository->threadsWithOwnerRow([THREAD, 'x']), 'thread is marked transferred');
	assertSameValue(OTHER_HASH, $serviceAs(OP_HASH)->ownerHash(THREAD, $postRepository->getPostByUid(1)), 'ownerHash follows the row');
});

testCase('rolesForThreads respects the transfer', function () use ($serviceAs) {
	assertSameValue([THREAD => threadRole::MOD], $serviceAs(OP_HASH)->rolesForThreads([THREAD]), 'old owner polls as mod');
	assertSameValue([THREAD => threadRole::OWNER], $serviceAs(OTHER_HASH)->rolesForThreads([THREAD]), 'new owner polls as owner');
});

echo "\nDeletion flags\n";

testCase('marking and counting OP deletions', function () use ($serviceAs, $postRepository, $repository) {
	$service = $serviceAs(OTHER_HASH);
	$service->markDeleted($postRepository->getPostByUid(2), threadRole::OWNER, OTHER_HASH);
	$service->markDeleted($postRepository->getPostByUid(4), threadRole::MOD, MOD_HASH);

	assertSameValue(2, $repository->countOpDeletions(THREAD), 'two hidden');
	assertSameValue([2, 4], $repository->opDeletedPostUids(THREAD), 'their uids');

	$deletion = threadModService::deletionOf($postRepository->getPostByUid(4));
	assertSameValue(threadRole::MOD, $deletion['role'], 'role read back');
	assertSameValue(MOD_HASH, $deletion['hash'], 'hash read back');
	assertSameValue(threadRole::OWNER, $service->authorRole($postRepository->getPostByUid(3), OTHER_HASH), 'author role follows the owner hash');
});

testCase('clearing one and clearing all', function () use ($serviceAs, $postRepository, $repository) {
	$service = $serviceAs(OTHER_HASH);
	$service->clearDeleted($postRepository->getPostByUid(2));
	assertSameValue(1, $repository->countOpDeletions(THREAD), 'one left');

	assertSameValue([4], $service->clearAllDeleted(THREAD), 'clearAll reports what it cleared');
	assertSameValue(0, $repository->countOpDeletions(THREAD), 'none left');
	assertSameValue([], $service->clearAllDeleted(THREAD), 'nothing to clear twice');
});

echo "\nStrip and revoke-all\n";

testCase('a stripped thread answers no role to anyone', function () use ($serviceAs, $postRepository, $repository) {
	$service = $serviceAs(OTHER_HASH);
	$service->setStripped($postRepository->getPostByUid(1), true);
	assertTrueValue($service->isStripped($postRepository->getPostByUid(1)), 'flag set');
	assertSameValue([], $serviceAs(OTHER_HASH)->rolesForThreads([THREAD]), 'owner polls as nothing while stripped');

	// the new owner's row, the old owner's mod seat and the granted mod
	assertSameValue(3, $repository->revokeAll(THREAD, null), 'every live row closed');
	assertSameValue([], $repository->listActive(THREAD), 'roster empty');
	// ownership falls back to the opening post once the owner row is gone
	assertSameValue(threadRole::OWNER, $serviceAs(OP_HASH)->resolveRole(THREAD), 'OP token owns again');

	$service->setStripped($postRepository->getPostByUid(1), false);
	assertTrueValue(!$service->isStripped($postRepository->getPostByUid(1)), 'flag cleared');
});

echo "\nCookie\n";

testCase('the tools page picker lists live replies only', function () use ($repository, $pdo, $t) {
	$numbers = static fn(): array => array_map(static fn(array $row): int => (int)$row['no'], $repository->listReplies(THREAD));
	assertSameValue([2, 3, 4], $numbers(), 'every reply, oldest first, without the opening post');

	$pdo->exec("INSERT INTO `{$t('DELETED_POSTS_TABLE')}` (post_uid, deleted_by, file_only, by_proxy) VALUES (3, NULL, 0, 0)");
	assertSameValue([2, 4], $numbers(), 'a staff-deleted reply is left out');
	$pdo->exec("DELETE FROM `{$t('DELETED_POSTS_TABLE')}` WHERE post_uid = 3");
});

testCase('a claim stamps the browser token onto a post that has none, and only then', function () use ($serviceAs, $postRepository, $pdo, $t) {
	$cookieless = $postRepository->getPostByUid(4);
	assertTrueValue(!$serviceAs('')->adoptOpeningPost($cookieless), 'a browser without a token stamps nothing');
	assertTrueValue($serviceAs(OTHER_HASH)->adoptOpeningPost($cookieless), 'the claiming browser\'s token is written');
	assertSameValue(OTHER_HASH, $postRepository->getPostByUid(4)->getVisitorTokenHash(), 'the post now carries it');
	assertTrueValue(!$serviceAs(MOD_HASH)->adoptOpeningPost($postRepository->getPostByUid(4)), 'an existing token is never replaced');
	assertTrueValue(!$serviceAs(MOD_HASH)->adoptOpeningPost($cookieless), 'nor by a caller holding a stale copy of the post');
	assertSameValue(OTHER_HASH, $postRepository->getPostByUid(4)->getVisitorTokenHash(), 'still the first claimant\'s');
	$pdo->exec("UPDATE `{$t('POST_TABLE')}` SET visitor_token_hash = '' WHERE post_uid = 4");
});

testCase('the hint cookie round-trips roles', function () use ($serviceAs) {
	$service = $serviceAs(OP_HASH, [threadModService::COOKIE => 'abcd:o,ef01:m,bogus,zz:x']);
	assertSameValue(['abcd' => threadRole::OWNER, 'ef01' => threadRole::MOD], $service->rememberedThreads(), 'parsed, junk dropped');
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
