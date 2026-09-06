<?php

/**
 * Integration tests for the ban system, against a real MariaDB.
 *
 * The unit suite covers what a ban row means once it is in hand (banEntry, banDuration,
 * ipPatternMatcher, the checkpoint registry). What only an engine can settle is the narrowing
 * query itself: whether a ban filed on one board reaches another, whether a wildcard pattern is
 * fetched as a candidate at all, whether a token ban follows a visitor onto a new address, and
 * whether an expired or revoked row stops being handed back.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/bans.php
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

// banService builds user-facing messages through _T(); the language layer is not booted here, so
// the unit suite's echoing stub stands in for it.
require_once dirname(__DIR__) . '/framework/i18nStub.php';

use Kokonotsuba\ban\banAppealRepository;
use Kokonotsuba\ban\banAppealStatus;
use Kokonotsuba\ban\banCheckpoint;
use Kokonotsuba\ban\banEntry;
use Kokonotsuba\ban\banRepository;
use Kokonotsuba\ban\banService;
use Kokonotsuba\ban\visitorTokenSigner;
use Kokonotsuba\cookie\cookieService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\request\request;

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
// Connection and schema
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

echo "\nBan system integration tests ({$databaseName})\n";

/** Start from nothing so the migration runner builds the schema it expects. */
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

$runner = new migrationRunner(
	$databaseConnection,
	new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']),
	new schemaInspector($databaseConnection, $databaseName),
	$tableNames,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (): void {}
);
$runner->up();

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const BOARD_A = 10;
const BOARD_B = 11;

$pdo->prepare(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name)
	VALUES (:uid, :identifier, :title, '')"
)->execute([':uid' => BOARD_A, ':identifier' => 'a', ':title' => 'Board A']);

$pdo->prepare(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name)
	VALUES (:uid, :identifier, :title, '')"
)->execute([':uid' => BOARD_B, ':identifier' => 'b', ':title' => 'Board B']);

$bans = new banRepository(
	$databaseConnection,
	$tableNames['BAN_TABLE'],
	$tableNames['BAN_APPEAL_TABLE'],
	$tableNames['ACCOUNT_TABLE'],
	$tableNames['POST_TABLE'],
	$tableNames['BOARD_TABLE']
);

$appeals = new banAppealRepository(
	$databaseConnection,
	$tableNames['BAN_APPEAL_TABLE'],
	$tableNames['BAN_TABLE'],
	$tableNames['ACCOUNT_TABLE'],
	$tableNames['BOARD_TABLE']
);

$signer = new visitorTokenSigner('koko integration test secret');

/** A service speaking for one address, carrying the given cookie value verbatim. */
$makeRawService = static function (string $ip, string $cookieValue) use ($bans, $appeals, $signer): banService {
	return new banService(
		$bans,
		$appeals,
		new request([], [], ['REMOTE_ADDR' => $ip, 'REQUEST_TIME' => time()]),
		new cookieService($cookieValue === '' ? [] : ['koko' => $cookieValue]),
		'koko',
		730,
		$signer
	);
};

/** The same, for a visitor carrying a properly signed token - which is every ordinary visitor. */
$makeService = static function (string $ip, string $cookieToken = '') use ($makeRawService, $signer): banService {
	return $makeRawService($ip, $cookieToken === '' ? '' : $signer->sign($cookieToken));
};

$clearBans = static function () use ($pdo, $tableNames): void {
	$pdo->exec("DELETE FROM `{$tableNames['BAN_APPEAL_TABLE']}`");
	$pdo->exec("DELETE FROM `{$tableNames['BAN_TABLE']}`");
};

/**
 * A post to file a ban from, carrying the browser token hash it was made with.
 *
 * A ban is only ever tied to a browser through a post, so every tie test needs one. Pass '' for
 * a post made by a browser keeping no cookie, and null for one predating the column.
 */
$tokenPostNo = 2000;
$makeTokenPost = static function (string $ip, ?string $tokenHash) use ($pdo, $tableNames, &$tokenPostNo): int {
	$no = ++$tokenPostNo;
	$threadUid = 'thread-' . $no;

	$pdo->prepare(
		"INSERT INTO `{$tableNames['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID)
		VALUES (?, ?, 0, ?)"
	)->execute([$threadUid, $no, BOARD_A]);

	$pdo->prepare(
		"INSERT INTO `{$tableNames['POST_TABLE']}`
		(no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host, visitor_token_hash)
		VALUES (?, ?, ?, 1, NOW(), '', '', '', '', '', '', ?, ?)"
	)->execute([$no, BOARD_A, $threadUid, $ip, $tokenHash]);

	return (int) $pdo->lastInsertId();
};

// ---------------------------------------------------------------------------
// Scope
// ---------------------------------------------------------------------------

echo "\nScope\n";

testCase('a board ban applies on its own board and nowhere else', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.5');
	$service->fileBan('192.0.2.5', BOARD_A, ['post'], time() + 3600, 'test', null);

	assertTrueValue(
		$service->findBlockingBan(banCheckpoint::POST, BOARD_A) !== null,
		'the ban did not apply on the board it was filed for'
	);
	assertSameValue(
		null,
		$service->findBlockingBan(banCheckpoint::POST, BOARD_B),
		'a board ban leaked onto another board'
	);
});

testCase('a global ban applies on every board', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.6');
	$service->fileBan('192.0.2.6', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	assertTrueValue($service->findBlockingBan(banCheckpoint::POST, BOARD_A) !== null, 'not applied on board A');
	assertTrueValue($service->findBlockingBan(banCheckpoint::POST, BOARD_B) !== null, 'not applied on board B');
	assertTrueValue($service->findBlockingBan(banCheckpoint::POST) !== null, 'not applied with no board named');
});

// ---------------------------------------------------------------------------
// Checkpoints
// ---------------------------------------------------------------------------

echo "\nCheckpoints\n";

testCase('a ban only stops the checkpoints it names', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.7');
	$service->fileBan('192.0.2.7', GLOBAL_BOARD_UID, ['post', 'report'], time() + 3600, 'test', null);

	assertTrueValue($service->findBlockingBan(banCheckpoint::POST) !== null, 'posting was not blocked');
	assertTrueValue($service->findBlockingBan(banCheckpoint::REPORT) !== null, 'reporting was not blocked');
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::SOUDANE), 'voting was blocked without being named');
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::PM), 'PMs were blocked without being named');
});

testCase('a checkpoint the registry does not know is not stored', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('192.0.2.8');
	$banId = $service->fileBan('192.0.2.8', GLOBAL_BOARD_UID, ['post', 'nonsense'], time() + 3600, 'test', null);

	assertSameValue(['post'], $bans->findById($banId)->checkpoints, 'an unknown checkpoint was stored');
});

testCase('a warning is filed with no checkpoints and blocks nothing', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('192.0.2.9');
	$banId = $service->fileBan('192.0.2.9', BOARD_A, ['post'], null, 'behave', null, null, false, true);

	$ban = $bans->findById($banId);

	assertTrueValue($ban->isWarning, 'the row was not marked as a warning');
	assertSameValue([], $ban->checkpoints, 'a warning kept its checkpoints');
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::POST, BOARD_A), 'a warning blocked posting');
});

testCase('an unread warning interrupts once, then stops', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('192.0.2.20');
	$banId = $service->fileBan('192.0.2.20', BOARD_A, [], null, 'behave', null, null, false, true);

	// It blocks nothing, so it is not a blocking ban - but it is still owed to them.
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::POST, BOARD_A), 'a warning blocked posting');
	assertTrueValue($service->findUnreadWarning(BOARD_A) !== null, 'an unread warning was not delivered');

	$service->markSeen($bans->findById($banId));

	assertSameValue(null, $service->findUnreadWarning(BOARD_A), 'a warning was delivered twice');
});

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

echo "\nLifecycle\n";

testCase('an expired ban stops being enforced', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.10');
	$service->fileBan('192.0.2.10', GLOBAL_BOARD_UID, ['post'], time() - 60, 'test', null);

	assertSameValue(null, $service->findBlockingBan(banCheckpoint::POST), 'an expired ban was still enforced');
});

testCase('a lapsed ban is still owed one last notice', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('192.0.2.13');
	$banId = $service->fileBan('192.0.2.13', GLOBAL_BOARD_UID, ['post'], time() - 60, 'test', null);

	// It stops nothing and does not read as a ban any more, but it has one interruption left in
	// it: whoever it held is told it is over before it lets go.
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::POST), 'a lapsed ban was still enforced');
	assertSameValue(false, $service->isBanned(), 'a lapsed ban still read as a ban');
	assertTrueValue($service->findLapsedAwaitingNotice(banCheckpoint::POST) !== null, 'the expiry notice was not owed');

	$service->markExpiryNoticeSeen($bans->findById($banId));

	assertTrueValue($bans->findById($banId)->hasSeenExpiryNotice(), 'the telling was not recorded');
	assertSameValue(
		null,
		$service->findLapsedAwaitingNotice(banCheckpoint::POST),
		'the expiry notice was owed a second time'
	);
});

testCase('the notice is owed only to what the ban actually blocked', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.14');
	$service->fileBan('192.0.2.14', GLOBAL_BOARD_UID, ['post'], time() - 60, 'test', null);

	assertSameValue(
		null,
		$service->findLapsedAwaitingNotice(banCheckpoint::SOUDANE),
		'a lapsed posting ban interrupted voting'
	);
});

testCase('a lapsed mute owes nothing', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.15');
	$service->fileBan(
		'192.0.2.15', GLOBAL_BOARD_UID, ['post'], time() - 60, 'flood', null, null, false, false, true
	);

	// A mute is thrown away once it lapses, so there is nothing left to announce.
	assertSameValue(null, $service->findLapsedAwaitingNotice(banCheckpoint::POST), 'a lapsed mute interrupted posting');
});

testCase('putting a ban back in force restores the notice it owes', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('192.0.2.16');
	$banId = $service->fileBan('192.0.2.16', GLOBAL_BOARD_UID, ['post'], time() - 60, 'test', null);

	$service->markExpiryNoticeSeen($bans->findById($banId));
	$service->editBan($bans->findById($banId), ['expiresAt' => time() + 3600]);

	assertSameValue(null, $bans->findById($banId)->expirySeenAt, 'the notice was not owed again');
	assertTrueValue($service->findBlockingBan(banCheckpoint::POST) !== null, 'the ban was not back in force');
});

testCase('a permanent ban has no expiry and keeps applying', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('192.0.2.11');
	$banId = $service->fileBan('192.0.2.11', GLOBAL_BOARD_UID, ['post'], null, 'test', null);

	assertSameValue(null, $bans->findById($banId)->expiresAt, 'a permanent ban was given an expiry');
	assertTrueValue($bans->findById($banId)->isPermanent(), 'the row did not read as permanent');
	assertTrueValue($service->findBlockingBan(banCheckpoint::POST) !== null, 'a permanent ban was not enforced');
});

testCase('revoking lifts a ban and records who did it', function () use ($makeService, $clearBans, $bans, $pdo, $tableNames): void {
	$clearBans();

	$accountId = (int) $pdo->query("SELECT id FROM `{$tableNames['ACCOUNT_TABLE']}` LIMIT 1")->fetchColumn();
	$accountId = $accountId > 0 ? $accountId : null;

	$service = $makeService('192.0.2.12');
	$banId = $service->fileBan('192.0.2.12', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$revoked = $service->revokeBans([$banId], $accountId);

	assertSameValue(1, count($revoked), 'revoking did not report the ban it lifted');
	assertTrueValue($bans->findById($banId)->isRevoked(), 'the row was not marked revoked');
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::POST), 'a revoked ban was still enforced');
});

testCase('revoking an already-revoked ban reports nothing', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('192.0.2.13');
	$banId = $service->fileBan('192.0.2.13', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$service->revokeBans([$banId], null);

	assertSameValue([], $service->revokeBans([$banId], null), 'a second revocation claimed work');
});

// ---------------------------------------------------------------------------
// Matching
// ---------------------------------------------------------------------------

echo "\nMatching\n";

testCase('an exact ban catches only that address', function () use ($makeService, $clearBans): void {
	$clearBans();

	$makeService('198.51.100.1')->fileBan('198.51.100.1', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	assertTrueValue($makeService('198.51.100.1')->findBlockingBan(banCheckpoint::POST) !== null, 'the banned address got through');
	assertSameValue(null, $makeService('198.51.100.2')->findBlockingBan(banCheckpoint::POST), 'a neighbouring address was caught');
});

testCase('a wildcard ban is fetched as a candidate and matched in PHP', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$banId = $makeService('198.51.100.1')->fileBan('198.51.100.*', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	assertTrueValue($bans->findById($banId)->isWildcard, 'the pattern was not flagged as a wildcard');
	assertTrueValue($makeService('198.51.100.77')->findBlockingBan(banCheckpoint::POST) !== null, 'the range did not catch its own address');
	assertSameValue(null, $makeService('198.51.101.77')->findBlockingBan(banCheckpoint::POST), 'the range caught an address outside it');
});

// ---------------------------------------------------------------------------
// Visitor tokens
// ---------------------------------------------------------------------------

echo "\nVisitor tokens\n";

testCase('a cookie the engine did not sign is no token at all', function () use ($makeRawService, $clearBans): void {
	$clearBans();

	$invented = str_repeat('c', 32);

	// Every shape somebody might try by hand: a bare id, a made-up signature, and junk. The bare
	// id is the shape a token from before signing had, and it gets nothing now either.
	foreach ([$invented, $invented . '.' . str_repeat('d', 16), 'not-a-token'] as $tampered) {
		assertSameValue(
			null,
			$makeRawService('198.51.100.20', $tampered)->getVisitorToken(),
			'an invented cookie was taken for a token'
		);
	}
});

testCase('a ban cannot be dodged by writing a fresh token in by hand', function () use ($makeService, $makeRawService, $makeTokenPost, $clearBans, $signer): void {
	$clearBans();

	$token = str_repeat('5', 32);
	$postUid = $makeTokenPost('198.51.100.21', $signer->tokenHash($token));

	$makeService('198.51.100.21', $token)->fileBan(
		'198.51.100.21', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid, true
	);

	// Editing the cookie to anything at all leaves them with no token, so the address is all
	// that is left to go on - and the ban was filed against it.
	assertTrueValue(
		$makeRawService('198.51.100.21', str_repeat('6', 32))->findBlockingBan(banCheckpoint::POST) !== null,
		'an edited cookie shook the ban off'
	);

	assertSameValue(
		null,
		$makeRawService('198.51.100.21', str_repeat('6', 32))->getVisitorToken(),
		'the invented token was carried through to the ban match'
	);
});

testCase('a ban ticked for the browser follows it to a new address', function () use ($makeService, $makeTokenPost, $clearBans, $bans, $signer): void {
	$clearBans();

	$token = str_repeat('b', 32);
	$postUid = $makeTokenPost('203.0.113.2', $signer->tokenHash($token));

	$banId = $makeService('203.0.113.2', $token)->fileBan(
		'203.0.113.2', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid, true
	);

	assertSameValue(
		$signer->tokenHash($token),
		$bans->findById($banId)->visitorTokenHash,
		'the ban was not tied to the browser that made the post'
	);

	// Same browser, different address: still caught, and nothing had to be tracking it.
	assertTrueValue(
		$makeService('203.0.113.99', $token)->findBlockingBan(banCheckpoint::POST) !== null,
		'the browser ban did not follow to a new address'
	);

	// Different browser on the banned address is still caught by the address itself.
	assertTrueValue(
		$makeService('203.0.113.2')->findBlockingBan(banCheckpoint::POST) !== null,
		'the address half of the ban stopped working'
	);

	// Neither the address nor the browser: through.
	assertSameValue(
		null,
		$makeService('203.0.113.98', str_repeat('c', 32))->findBlockingBan(banCheckpoint::POST),
		'an unrelated visitor was caught'
	);
});

testCase('a browser ban does not catch a stranger sharing the address', function () use ($makeService, $makeTokenPost, $clearBans, $signer): void {
	$clearBans();

	$banned = str_repeat('7', 32);
	$stranger = str_repeat('8', 32);
	$postUid = $makeTokenPost('203.0.113.6', $signer->tokenHash($banned));

	$makeService('203.0.113.6', $banned)->fileBan(
		'203.0.113.6', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid, true
	);

	// Somebody else on the address the banned browser moved to: their browser was never banned
	// and the ban was filed against a different address, so nothing applies to them.
	assertSameValue(
		null,
		$makeService('203.0.113.66', $stranger)->findBlockingBan(banCheckpoint::POST),
		'a browser ban caught a different browser on the same address'
	);

	// The banned browser itself is caught there.
	assertTrueValue(
		$makeService('203.0.113.66', $banned)->findBlockingBan(banCheckpoint::POST) !== null,
		'the browser ban stopped following the browser it was filed against'
	);
});

testCase('a ban filed without the box ticked carries no browser', function () use ($makeService, $makeTokenPost, $clearBans, $bans, $signer): void {
	$clearBans();

	$token = str_repeat('d', 32);
	$postUid = $makeTokenPost('203.0.113.3', $signer->tokenHash($token));

	$banId = $makeService('203.0.113.3', $token)->fileBan(
		'203.0.113.3', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid
	);

	assertSameValue(null, $bans->findById($banId)->visitorTokenHash, 'a browser was attached without being asked for');
	assertSameValue(
		null,
		$makeService('203.0.113.97', $token)->findBlockingBan(banCheckpoint::POST),
		'the ban followed the browser it was never tied to'
	);
});

testCase('there is nothing to tie to without a post that recorded one', function () use ($makeService, $makeTokenPost, $clearBans, $bans): void {
	$clearBans();

	$token = str_repeat('1', 32);

	// A post from a browser keeping no cookie, a post predating the column, and no post at all.
	$cases = [
		'cookieless' => $makeTokenPost('203.0.113.7', ''),
		'unrecorded' => $makeTokenPost('203.0.113.7', null),
		'no post'    => null,
	];

	foreach ($cases as $label => $postUid) {
		$banId = $makeService('203.0.113.7', $token)->fileBan(
			'203.0.113.7', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid, true
		);

		assertSameValue(null, $bans->findById($banId)->visitorTokenHash, "a browser was tied from a {$label} post");
	}

	// The address still bans, and no other browser inherits it.
	assertSameValue(
		null,
		$makeService('203.0.113.96', $token)->findBlockingBan(banCheckpoint::POST),
		'a ban tied to nothing followed a browser anyway'
	);
});

// ---------------------------------------------------------------------------
// Innocent visitors
// ---------------------------------------------------------------------------
//
// The browser tie is the one part of a ban that reaches past the address it was written
// against, so it is the one part that can catch somebody who was never there. Every case here
// is a visitor who did nothing, checking that nothing reaches them.

echo "\nInnocent visitors\n";

testCase('a browser ban does not catch a visitor keeping no cookie', function () use ($makeService, $makeTokenPost, $clearBans, $signer): void {
	$clearBans();

	$banned = str_repeat('3', 32);
	$postUid = $makeTokenPost('203.0.113.80', $signer->tokenHash($banned));

	$makeService('203.0.113.80', $banned)->fileBan(
		'203.0.113.80', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid, true
	);

	// Somebody else, somewhere else, holding nothing. The address is not theirs and the browser
	// is not theirs, so neither half of the ban has anything to say about them.
	assertSameValue(
		null,
		$makeService('203.0.113.81')->findBlockingBan(banCheckpoint::POST),
		'a browser ban caught a visitor who carries no cookie'
	);
});

testCase('visitors keeping no cookie are not each other', function () use ($makeService, $clearBans, $bans, $pdo, $tableNames): void {
	$clearBans();

	// A ban tied to the empty string is what a cookieless post would produce if '' were ever
	// let through as a tie. It is not - but if it were, every cookieless visitor alive would
	// answer to it at once, so the match is checked against a row written that way by hand.
	$banId = $makeService('203.0.113.82')->fileBan(
		'203.0.113.82', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null
	);
	$pdo->prepare("UPDATE `{$tableNames['BAN_TABLE']}` SET visitor_token_hash = '' WHERE ban_id = ?")->execute([$banId]);

	assertSameValue('', $bans->findById($banId)->visitorTokenHash, 'the row under test was not written');

	foreach (['203.0.113.83', '203.0.113.84', '198.51.100.7'] as $innocent) {
		assertSameValue(
			null,
			$makeService($innocent)->findBlockingBan(banCheckpoint::POST),
			"an empty tie caught a cookieless visitor at {$innocent}"
		);
	}

	// And the address it was actually filed against still bans, cookie or no cookie.
	assertTrueValue(
		$makeService('203.0.113.82')->findBlockingBan(banCheckpoint::POST) !== null,
		'the address half of the ban stopped working'
	);
});

testCase('an expired browser ban stops following the browser', function () use ($makeService, $makeTokenPost, $clearBans, $signer): void {
	$clearBans();

	$token = str_repeat('4', 32);
	$postUid = $makeTokenPost('203.0.113.85', $signer->tokenHash($token));

	$makeService('203.0.113.85', $token)->fileBan(
		'203.0.113.85', GLOBAL_BOARD_UID, ['post'], time() - 60, 'test', null, $postUid, true
	);

	// Lapsed, so it reaches neither the address it named nor the browser it followed.
	assertSameValue(
		null,
		$makeService('203.0.113.86', $token)->findBlockingBan(banCheckpoint::POST),
		'an expired ban kept following the browser'
	);
	assertSameValue(
		null,
		$makeService('203.0.113.85', $token)->findBlockingBan(banCheckpoint::POST),
		'an expired ban kept blocking its address'
	);
});

testCase('a revoked browser ban stops following the browser', function () use ($makeService, $makeTokenPost, $clearBans, $signer): void {
	$clearBans();

	$token = str_repeat('a', 32);
	$postUid = $makeTokenPost('203.0.113.87', $signer->tokenHash($token));

	$service = $makeService('203.0.113.87', $token);
	$banId = $service->fileBan(
		'203.0.113.87', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid, true
	);

	$service->revokeBans([$banId], null);

	assertSameValue(
		null,
		$makeService('203.0.113.88', $token)->findBlockingBan(banCheckpoint::POST),
		'a lifted ban kept following the browser'
	);
});

testCase('a browser ban on one board does not follow the browser onto another', function () use ($makeService, $makeTokenPost, $clearBans, $signer): void {
	$clearBans();

	$token = str_repeat('c', 32);
	$postUid = $makeTokenPost('203.0.113.89', $signer->tokenHash($token));

	$makeService('203.0.113.89', $token)->fileBan(
		'203.0.113.89', BOARD_A, ['post'], time() + 3600, 'test', null, $postUid, true
	);

	// The browser is banned, but only somewhere. Everywhere else it is an ordinary visitor.
	assertTrueValue(
		$makeService('203.0.113.90', $token)->findBlockingBan(banCheckpoint::POST, BOARD_A) !== null,
		'the browser ban did not apply on the board it was filed for'
	);
	assertSameValue(
		null,
		$makeService('203.0.113.90', $token)->findBlockingBan(banCheckpoint::POST, BOARD_B),
		'a board ban followed the browser onto another board'
	);
});

testCase('a range ban does not reach the range next door', function () use ($makeService, $clearBans): void {
	$clearBans();

	$makeService('192.168.1.5')->fileBan(
		'192.168.1.*', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null
	);

	// Inside the range that was written down.
	foreach (['192.168.1.0', '192.168.1.5', '192.168.1.255'] as $inside) {
		assertTrueValue(
			$makeService($inside)->findBlockingBan(banCheckpoint::POST) !== null,
			"the banned range let {$inside} through"
		);
	}

	// The addresses a string-prefix match would have swept up with it.
	foreach (['192.168.10.5', '192.168.11.5', '192.168.19.200', '192.168.2.5'] as $outside) {
		assertSameValue(
			null,
			$makeService($outside)->findBlockingBan(banCheckpoint::POST),
			"a /24 ban reached {$outside}"
		);
	}
});

testCase('a stray wildcard in a v6 pattern does not ban everyone', function () use ($makeService, $clearBans): void {
	$clearBans();

	// One address, one stray leading wildcard. It used to end the comparison there and match
	// every IPv6 address in existence.
	$makeService('2001:db8::1')->fileBan(
		'*:db8::1', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null
	);

	foreach (['::1', '2001:dead::1', '2600:1f18::4', 'fe80::1', '2001:db8::2'] as $innocent) {
		assertSameValue(
			null,
			$makeService($innocent)->findBlockingBan(banCheckpoint::POST),
			"a one-address v6 ban caught {$innocent}"
		);
	}

	assertTrueValue(
		$makeService('2001:db8::1')->findBlockingBan(banCheckpoint::POST) !== null,
		'the address the ban actually names stopped matching'
	);
});

// ---------------------------------------------------------------------------
// Seen state
// ---------------------------------------------------------------------------

echo "\nSeen state\n";

testCase('a visitor carrying a cookie is recorded as an ordinary sighting', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.10', str_repeat('e', 32));
	$banId = $service->fileBan('203.0.113.10', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$service->markSeen($bans->findById($banId));

	$ban = $bans->findById($banId);

	assertTrueValue($ban->hasBeenSeen(), 'the ban was not marked seen');
	assertSameValue(true, $ban->seenWithCookies, 'a visitor with a cookie was recorded as cookieless');
});

testCase('a visitor with no cookie is recorded as cookieless, not unknown', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.11');
	$banId = $service->fileBan('203.0.113.11', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$service->markSeen($bans->findById($banId));

	$ban = $bans->findById($banId);

	assertTrueValue($ban->hasBeenSeen(), 'the ban was not marked seen');
	assertSameValue(false, $ban->seenWithCookies, 'a cookieless visitor was not recorded as such');
});

testCase('only the first sighting is kept', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$banId = $makeService('203.0.113.12')->fileBan('203.0.113.12', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	// First without a cookie, then with one: the later visit must not rewrite the record.
	$makeService('203.0.113.12')->markSeen($bans->findById($banId));
	$firstSeen = $bans->findById($banId)->seenAt;

	$makeService('203.0.113.12', str_repeat('f', 32))->markSeen($bans->findById($banId));

	$ban = $bans->findById($banId);

	assertSameValue($firstSeen, $ban->seenAt, 'the sighting time was overwritten');
	assertSameValue(false, $ban->seenWithCookies, 'the cookie state was overwritten');
});

// ---------------------------------------------------------------------------
// Appeals
// ---------------------------------------------------------------------------

echo "\nAppeals\n";

testCase('an appeal can be filed against a live ban, once', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.20');
	$banId = $service->fileBan('203.0.113.20', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);
	$ban = $bans->findById($banId);

	assertSameValue(null, $service->getAppealBlocker($ban, 24), 'a live ban refused an appeal');

	$service->fileAppeal($ban, 'I am sorry', 24);

	assertTrueValue($service->getAppealBlocker($ban, 24) !== null, 'a second appeal was allowed while one was open');
});

testCase('a warning and a lifted ban cannot be appealed', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.21');

	$warningId = $service->fileBan('203.0.113.21', BOARD_A, [], null, 'behave', null, null, false, true);
	assertTrueValue($service->getAppealBlocker($bans->findById($warningId), 24) !== null, 'a warning accepted an appeal');

	$banId = $service->fileBan('203.0.113.21', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);
	$service->revokeBans([$banId], null);
	assertTrueValue($service->getAppealBlocker($bans->findById($banId), 24) !== null, 'a lifted ban accepted an appeal');
});

testCase('approving an appeal lifts the ban', function () use ($makeService, $clearBans, $bans, $appeals): void {
	$clearBans();

	$service = $makeService('203.0.113.22');
	$banId = $service->fileBan('203.0.113.22', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);
	$appealId = $service->fileAppeal($bans->findById($banId), 'let me back', 24);

	assertSameValue(1, $service->approveAppeals([$appealId], null, 'fine'), 'the appeal was not closed');
	assertTrueValue($bans->findById($banId)->isRevoked(), 'approving did not lift the ban');
	assertSameValue(banAppealStatus::APPROVED, $appeals->findById($appealId)->status, 'the appeal was not marked approved');
	assertSameValue(null, $service->findBlockingBan(banCheckpoint::POST), 'the lifted ban was still enforced');
});

testCase('an appeal is dropped once its ban has expired', function () use ($makeService, $clearBans, $bans, $appeals, $pdo, $tableNames): void {
	$clearBans();

	$service = $makeService('203.0.113.23');
	$liveId = $service->fileBan('203.0.113.23', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);
	$liveAppealId = $service->fileAppeal($bans->findById($liveId), 'still here', 24);
	$lapsedId = $service->fileBan('203.0.113.24', BOARD_A, ['post'], time() + 3600, 'test', null);
	$lapsedAppealId = $service->fileAppeal($bans->findById($lapsedId), 'let me back', 24);

	$pdo->prepare("UPDATE `{$tableNames['BAN_TABLE']}` SET expires_at = ? WHERE ban_id = ?")
		->execute([date('Y-m-d H:i:s', time() - 60), $lapsedId]);

	assertSameValue(1, $service->pruneExpiredAppeals(), 'the lapsed ban\'s appeal was not removed');
	assertSameValue(null, $appeals->findById($lapsedAppealId), 'the appeal outlived its ban');
	assertTrueValue($appeals->findById($liveAppealId) !== null, 'a live ban\'s appeal was removed');
	assertSameValue(0, $service->countPendingAppeals() - 1, 'the pending count still included the removed appeal');
	assertSameValue(0, $service->pruneExpiredAppeals(), 'pruning was not idempotent');
});

testCase('approving with a reduced sentence shortens the ban instead of lifting it', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.23');
	$banId = $service->fileBan('203.0.113.23', GLOBAL_BOARD_UID, ['post'], time() + 31536000, 'test', null);
	$appealId = $service->fileAppeal($bans->findById($banId), 'too long', 24);

	$newExpiry = time() + 3600;
	$service->approveAppeals([$appealId], null, 'shortened', $newExpiry);

	$ban = $bans->findById($banId);

	assertTrueValue(!$ban->isRevoked(), 'a reduced ban was lifted outright');
	assertSameValue($newExpiry, $ban->expiresAt, 'the ban was not moved to the new expiry');
	assertTrueValue($service->findBlockingBan(banCheckpoint::POST) !== null, 'a merely shortened ban stopped applying');
});

testCase('denying leaves the ban alone and starts the cooldown', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.24');
	$banId = $service->fileBan('203.0.113.24', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);
	$appealId = $service->fileAppeal($bans->findById($banId), 'please', 24);

	$service->denyAppeals([$appealId], null, 'no');

	$ban = $bans->findById($banId);

	assertTrueValue(!$ban->isRevoked(), 'denying lifted the ban');
	assertTrueValue($service->getAppealBlocker($ban, 24) !== null, 'the cooldown did not hold a re-appeal back');

	// With no cooldown configured, the same denial allows an immediate retry.
	assertSameValue(null, $service->getAppealBlocker($ban, 0), 'a zero cooldown still blocked a re-appeal');
});

testCase('a pending appeal shows on the ban row for the queue filter', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('203.0.113.25');
	$banId = $service->fileBan('203.0.113.25', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);
	$service->fileAppeal($bans->findById($banId), 'hello', 24);

	assertSameValue(1, $bans->findById($banId)->pendingAppealCount, 'the pending appeal was not counted on the ban');
	assertSameValue(1, $service->countPendingAppeals(), 'the queue count missed the appeal');

	$listed = $service->listBans(['status' => 'appealed'], 50, 0);

	assertSameValue(1, count($listed), 'the appealed filter did not find the ban');
	assertSameValue($banId, $listed[0]->id, 'the appealed filter returned the wrong ban');
});

// ---------------------------------------------------------------------------
// Editing
// ---------------------------------------------------------------------------

echo "\nEditing\n";

testCase('an edit can move the address, the length and every reason', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.10');
	$banId = $service->fileBan(
		'198.51.100.10', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'first go', null,
		null, false, false, false, 'public one', 'staff one'
	);

	$newExpiry = time() + 7200;

	$service->editBan($bans->findById($banId), [
		'ipPattern' => '198.51.100.11',
		'checkpoints' => ['post', 'report'],
		'expiresAt' => $newExpiry,
		'reason' => 'second go',
		'publicReason' => 'public two',
		'privateReason' => 'staff two',
	]);

	$ban = $bans->findById($banId);

	assertSameValue('198.51.100.11', $ban->ipPattern, 'the address was not moved');
	assertSameValue(['post', 'report'], $ban->checkpoints, 'the checkpoints were not changed');
	assertSameValue($newExpiry, $ban->expiresAt, 'the expiry was not moved');
	assertSameValue('second go', $ban->reason, 'the reason was not changed');
	assertSameValue('public two', $ban->publicReason, 'the public reason was not changed');
	assertSameValue('staff two', $ban->privateReason, 'the staff note was not changed');

	// And enforcement follows the edit rather than the original address.
	assertTrueValue($makeService('198.51.100.11')->findBlockingBan(banCheckpoint::POST) !== null, 'the moved ban does not apply');
	assertSameValue(null, $makeService('198.51.100.10')->findBlockingBan(banCheckpoint::POST), 'the old address is still banned');
});

testCase('editing an address to a range makes it a range ban', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.20');
	$banId = $service->fileBan('198.51.100.20', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$service->editBan($bans->findById($banId), ['ipPattern' => '198.51.100.*']);

	assertTrueValue($bans->findById($banId)->isWildcard, 'the edited pattern was not flagged as a wildcard');
	assertTrueValue($makeService('198.51.100.99')->findBlockingBan(banCheckpoint::POST) !== null, 'the widened ban does not catch the range');
});

testCase('an edit only touches the fields it names', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.30');
	$banId = $service->fileBan(
		'198.51.100.30', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'keep me', null,
		null, false, false, false, 'keep public', 'keep staff'
	);

	$service->editBan($bans->findById($banId), ['reason' => 'changed']);

	$ban = $bans->findById($banId);

	assertSameValue('changed', $ban->reason, 'the reason was not changed');
	assertSameValue('keep public', $ban->publicReason, 'an untouched public reason was blanked');
	assertSameValue('keep staff', $ban->privateReason, 'an untouched staff note was blanked');
	assertSameValue(['post'], $ban->checkpoints, 'untouched checkpoints were blanked');
});

testCase('an edit cannot blank the address', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.40');
	$banId = $service->fileBan('198.51.100.40', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$threw = false;

	try {
		$service->editBan($bans->findById($banId), ['ipPattern' => '   ']);
	} catch (Kokonotsuba\error\BoardException $e) {
		$threw = true;
	}

	assertTrueValue($threw, 'a blank address was accepted');
	assertSameValue('198.51.100.40', $bans->findById($banId)->ipPattern, 'the address was blanked anyway');
});

testCase('an edit can tie and untie the browser', function () use ($makeService, $makeTokenPost, $clearBans, $bans, $signer): void {
	$clearBans();

	$token = str_repeat('9', 32);
	$postUid = $makeTokenPost('198.51.100.50', $signer->tokenHash($token));

	$service = $makeService('198.51.100.50', $token);
	$banId = $service->fileBan('198.51.100.50', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null, $postUid);

	assertSameValue(null, $bans->findById($banId)->visitorTokenHash, 'a browser was tied without being asked for');

	$service->editBan($bans->findById($banId), ['tieVisitorToken' => true]);
	assertSameValue($signer->tokenHash($token), $bans->findById($banId)->visitorTokenHash, 'the browser was not tied on edit');

	$service->editBan($bans->findById($banId), ['tieVisitorToken' => false]);
	assertSameValue(null, $bans->findById($banId)->visitorTokenHash, 'the browser was not cleared on edit');
});

// ---------------------------------------------------------------------------
// Refusing appeals
// ---------------------------------------------------------------------------

echo "\nRefusing appeals\n";

testCase('a ban filed with appeals refused will not take one', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.60');
	$banId = $service->fileBan(
		'198.51.100.60', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null,
		null, false, false, false, '', '', true
	);

	$ban = $bans->findById($banId);

	assertTrueValue($ban->rejectsAppeals, 'the flag was not stored');
	assertTrueValue($service->getAppealBlocker($ban, 24) !== null, 'a ban refusing appeals accepted one');
});

testCase('appeals are accepted by default', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.61');
	$banId = $service->fileBan('198.51.100.61', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$ban = $bans->findById($banId);

	assertTrueValue(!$ban->rejectsAppeals, 'appeals were refused without being asked for');
	assertSameValue(null, $service->getAppealBlocker($ban, 24), 'a default ban refused an appeal');
});

testCase('an edit can turn appeals off and back on', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.62');
	$banId = $service->fileBan('198.51.100.62', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'test', null);

	$service->editBan($bans->findById($banId), ['rejectsAppeals' => true]);
	assertTrueValue($service->getAppealBlocker($bans->findById($banId), 24) !== null, 'appeals were not turned off');

	$service->editBan($bans->findById($banId), ['rejectsAppeals' => false]);
	assertSameValue(null, $service->getAppealBlocker($bans->findById($banId), 24), 'appeals were not turned back on');
});

// ---------------------------------------------------------------------------
// Public notices
// ---------------------------------------------------------------------------

echo "\nPublic notices\n";

testCase('a public reason is looked up by the post it was filed over', function () use ($makeService, $clearBans, $pdo, $tableNames): void {
	$clearBans();

	$service = $makeService('198.51.100.70');
	$service->fileBan(
		'198.51.100.70', BOARD_A, ['post'], time() + 3600, 'test', null,
		null, false, false, false, '<p>banned for this</p>'
	);

	// The ban above carries no post, so nothing should come back for any post.
	assertSameValue([], $service->getPublicReasonsForPosts([1, 2, 3]), 'a postless ban leaked a notice');
	assertSameValue([], $service->getPublicReasonsForPosts([]), 'an empty batch queried anything at all');
});

testCase('a revoked ban stops publishing its notice', function () use ($makeService, $clearBans, $bans, $pdo, $tableNames): void {
	$clearBans();

	$service = $makeService('198.51.100.71');
	$banId = $service->fileBan(
		'198.51.100.71', GLOBAL_BOARD_UID, ['post'], time() - 60, 'test', null,
		null, false, false, false, '<p>banned for this</p>'
	);

	// Attach it to a notional post id directly; the posts table is empty in this fixture.
	$pdo->prepare("UPDATE `{$tableNames['BAN_TABLE']}` SET post_uid = NULL WHERE ban_id = ?")->execute([$banId]);

	// An expired ban keeps its notice - they were still banned for that post.
	assertTrueValue($bans->findById($banId)->publicReason !== '', 'the public reason was lost');

	$service->revokeBans([$banId], null);

	assertSameValue([], $service->getPublicReasonsForPosts([1]), 'a revoked ban still published its notice');
});

// ---------------------------------------------------------------------------
// The post a ban was filed on
// ---------------------------------------------------------------------------

echo "\nBanned posts\n";

/** A thread and its OP, so a ban has a real post to hang off (bans.post_uid is a foreign key). */
$makePost = static function (int $boardUid, int $postNumber) use ($pdo, $tableNames): int {
	$threadUid = "thread-{$boardUid}-{$postNumber}";

	$pdo->prepare(
		"INSERT INTO `{$tableNames['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID)
		VALUES (?, ?, 0, ?)"
	)->execute([$threadUid, $postNumber, $boardUid]);

	$pdo->prepare(
		"INSERT INTO `{$tableNames['POST_TABLE']}`
			(no, boardUID, thread_uid, is_op, root, pwd, now, name, email, sub, com, host)
		VALUES (?, ?, ?, 1, NOW(), '', '', 'Anonymous', '', '', 'banned for this', '198.51.100.90')"
	)->execute([$postNumber, $boardUid, $threadUid]);

	return (int) $pdo->lastInsertId();
};

testCase('the post a ban names is released to the address it banned', function () use ($makeService, $clearBans, $makePost): void {
	$clearBans();

	$postUid = $makePost(BOARD_A, 1001);
	$service = $makeService('198.51.100.90');
	$service->fileBan('198.51.100.90', BOARD_A, ['post'], time() + 3600, 'test', null, $postUid);

	assertTrueValue($service->viewerWasBannedForPost($postUid), 'the banned party was refused their own post');
	assertSameValue(false, $service->viewerWasBannedForPost($postUid + 5000), 'a post the ban does not name was released');
	assertSameValue(false, $service->viewerWasBannedForPost(0), 'a missing post uid was treated as a match');
});

testCase('nobody else is let at the post through that ban', function () use ($makeService, $clearBans, $makePost): void {
	$clearBans();

	$postUid = $makePost(BOARD_A, 1002);
	$makeService('198.51.100.91')->fileBan('198.51.100.91', BOARD_A, ['post'], time() + 3600, 'test', null, $postUid);

	assertSameValue(
		false,
		$makeService('198.51.100.92')->viewerWasBannedForPost($postUid),
		'an address with no ban was let at the post'
	);
});

testCase('an expired ban still shows its post, a revoked one does not', function () use ($makeService, $clearBans, $makePost): void {
	$clearBans();

	$postUid = $makePost(BOARD_A, 1003);
	$service = $makeService('198.51.100.93');
	$banId = $service->fileBan('198.51.100.93', BOARD_A, ['post'], time() - 60, 'test', null, $postUid);

	// The ban page keeps showing a lapsed ban, so its post has to stay with it.
	assertTrueValue($service->viewerWasBannedForPost($postUid), 'an expired ban stopped showing its post');

	$service->revokeBans([$banId], null);

	assertSameValue(false, $service->viewerWasBannedForPost($postUid), 'a lifted ban still released its post');
});

// ---------------------------------------------------------------------------
// Mutes
// ---------------------------------------------------------------------------

echo "\nMutes\n";

testCase('a mute is enforced while it runs', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.80');
	$ids = $service->fileBans(['198.51.100.80'], GLOBAL_BOARD_UID, ['post'], time() + 600, 'flooding', null, true);

	assertTrueValue($bans->findById($ids[0])->isMute, 'the row was not marked as a mute');
	assertTrueValue($service->findBlockingBan(banCheckpoint::POST) !== null, 'a live mute did not block posting');
});

testCase('a lapsed mute is swept away rather than kept', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.81');
	$ids = $service->fileBans(['198.51.100.81'], GLOBAL_BOARD_UID, ['post'], time() - 60, 'old flood', null, true);

	// Filing a fresh mute is what triggers the sweep, so the lapsed one goes with it.
	$service->fileBans(['198.51.100.82'], GLOBAL_BOARD_UID, ['post'], time() + 600, 'new flood', null, true);

	assertSameValue(null, $bans->findById($ids[0]), 'a lapsed mute was left in the table');
	assertSameValue(1, $service->countBans([]), 'the sweep took the live mute with it');
});

testCase('the sweep leaves ordinary expired bans alone', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.83');
	$banId = $service->fileBan('198.51.100.83', GLOBAL_BOARD_UID, ['post'], time() - 60, 'a real ban', null);

	assertSameValue(0, $service->pruneExpiredMutes(), 'the sweep claimed a ban that was not a mute');
	assertTrueValue($bans->findById($banId) !== null, 'an expired ban was deleted along with the mutes');
});

testCase('a mute cannot be appealed', function () use ($makeService, $clearBans, $bans): void {
	$clearBans();

	$service = $makeService('198.51.100.84');
	$ids = $service->fileBans(['198.51.100.84'], GLOBAL_BOARD_UID, ['post'], time() + 600, 'flooding', null, true);

	assertTrueValue(
		$service->getAppealBlocker($bans->findById($ids[0]), 24) !== null,
		'a mute accepted an appeal it would outlive'
	);
});

// ---------------------------------------------------------------------------
// Listing
// ---------------------------------------------------------------------------

echo "\nListing\n";

testCase('the status filters partition the table', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.30');

	$service->fileBan('203.0.113.30', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'live', null);
	$service->fileBan('203.0.113.31', GLOBAL_BOARD_UID, ['post'], time() - 3600, 'gone', null);
	$lifted = $service->fileBan('203.0.113.32', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'lifted', null);
	$service->revokeBans([$lifted], null);

	assertSameValue(3, $service->countBans(['status' => 'all']), 'the unfiltered count was wrong');
	assertSameValue(1, $service->countBans(['status' => 'active']), 'the active count was wrong');
	assertSameValue(1, $service->countBans(['status' => 'expired']), 'the expired count was wrong');
	assertSameValue(1, $service->countBans(['status' => 'revoked']), 'the revoked count was wrong');
});

testCase('the ip filter is exact, not a substring match', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.70');
	$service->fileBan('203.0.113.7', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'short', null);
	$service->fileBan('203.0.113.70', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'long', null);
	$service->fileBan('203.0.113.70', BOARD_A, ['post'], time() + 3600, 'again', null);

	// Filtering for .7 must not drag in .70, which a LIKE would.
	assertSameValue(1, $service->countBans(['ip' => '203.0.113.7']), 'the ip filter matched a longer address');
	assertSameValue(2, $service->countBans(['ip' => '203.0.113.70']), 'the ip filter missed a ban on the address');
	assertSameValue(0, $service->countBans(['ip' => '203.0.113.71']), 'an unbanned address matched something');
});

testCase('the board filter narrows to one scope', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.40');
	$service->fileBan('203.0.113.40', BOARD_A, ['post'], time() + 3600, 'a', null);
	$service->fileBan('203.0.113.41', BOARD_B, ['post'], time() + 3600, 'b', null);
	$service->fileBan('203.0.113.42', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'g', null);

	assertSameValue(1, $service->countBans(['boardUid' => BOARD_A]), 'the board A filter was wrong');
	assertSameValue(1, $service->countBans(['boardUid' => GLOBAL_BOARD_UID]), 'the global filter was wrong');
	assertSameValue(3, $service->countBans([]), 'no filter did not mean every board');
});

testCase('search matches the pattern and the reason', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.50');
	$service->fileBan('203.0.113.50', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'flooding the board', null);
	$service->fileBan('198.51.100.50', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'spam', null);

	assertSameValue(1, $service->countBans(['search' => 'flooding']), 'the reason was not searched');
	assertSameValue(1, $service->countBans(['search' => '198.51']), 'the pattern was not searched');
	assertSameValue(0, $service->countBans(['search' => 'nothing here']), 'a miss matched something');
});

testCase('the board filter takes a list of scopes', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.45');
	$service->fileBan('203.0.113.45', BOARD_A, ['post'], time() + 3600, 'a', null);
	$service->fileBan('203.0.113.46', BOARD_B, ['post'], time() + 3600, 'b', null);
	$service->fileBan('203.0.113.47', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'g', null);

	assertSameValue(2, $service->countBans(['boards' => [BOARD_A, GLOBAL_BOARD_UID]]), 'the scope list was not honoured');
	assertSameValue(1, $service->countBans(['boards' => [BOARD_B]]), 'a one-scope list was wrong');
	assertSameValue(3, $service->countBans(['boards' => []]), 'an empty list did not mean every board');
});

testCase('the general search reaches every reason and the staff who filed', function () use ($makeService, $clearBans, $pdo, $tableNames): void {
	$clearBans();

	$pdo->exec("INSERT INTO `{$tableNames['ACCOUNT_TABLE']}` (username, role, password_hash) VALUES ('Griselda', 4, 'x')");
	$accountId = (int) $pdo->lastInsertId();

	$service = $makeService('203.0.113.51');
	$service->fileBan('203.0.113.51', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'flooding', $accountId);
	$service->fileBan('198.51.100.52', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'spam', null, null, false, false, false, 'banned for this post');
	$service->fileBan('198.51.100.53', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'spam', null, null, false, false, false, '', 'sockpuppet of #1');

	assertSameValue(1, $service->countBans(['general' => 'flooding']), 'the reason was not searched');
	assertSameValue(1, $service->countBans(['general' => '203.0.113.51']), 'the address was not searched');
	assertSameValue(1, $service->countBans(['general' => 'banned for this']), 'the public notice was not searched');
	assertSameValue(1, $service->countBans(['general' => 'sockpuppet']), 'the staff note was not searched');
	assertSameValue(1, $service->countBans(['general' => 'Griselda']), 'the staff who filed it was not searched');
	assertSameValue(0, $service->countBans(['general' => 'nothing here']), 'a miss matched something');
});

testCase('the general search leaves addresses alone when they are hidden', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.55');
	$service->fileBan('203.0.113.55', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'flooding', null);

	// A janitor who may not see addresses must not be able to ask whether one is banned.
	assertSameValue(
		0,
		$service->countBans(['general' => '203.0.113.55', 'searchAddresses' => false]),
		'the address was searched for someone who may not see addresses'
	);
	assertSameValue(
		1,
		$service->countBans(['general' => 'flooding', 'searchAddresses' => false]),
		'the reason stopped being searched too'
	);
	// The field is ignored rather than made to match nothing: an unnarrowed table tells the
	// asker nothing, while an empty one would have answered their question.
	assertSameValue(
		1,
		$service->countBans(['ip' => '203.0.113.55', 'searchAddresses' => false]),
		'the address filter narrowed the table for someone who may not see addresses'
	);
});

testCase('the kind filter tells bans, warnings and mutes apart', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.56');
	$service->fileBan('203.0.113.56', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'ban', null);
	$service->fileBan('203.0.113.57', GLOBAL_BOARD_UID, [], null, 'warning', null, null, false, true);
	$service->fileBans(['203.0.113.58'], GLOBAL_BOARD_UID, ['post'], time() + 600, 'mute', null, true);

	assertSameValue(3, $service->countBans(['kind' => 'all']), 'the unfiltered count was wrong');
	assertSameValue(1, $service->countBans(['kind' => 'ban']), 'the ban filter caught more than bans');
	assertSameValue(1, $service->countBans(['kind' => 'warning']), 'the warning filter was wrong');
	assertSameValue(1, $service->countBans(['kind' => 'mute']), 'the mute filter was wrong');
});

testCase('the checkpoint filter matches what a ban blocks', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.59');
	$service->fileBan('203.0.113.59', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'posting', null);
	$service->fileBan('203.0.113.60', GLOBAL_BOARD_UID, ['report', 'soudane'], time() + 3600, 'reporting', null);
	$service->fileBan('203.0.113.61', GLOBAL_BOARD_UID, [], null, 'warning', null, null, false, true);

	assertSameValue(1, $service->countBans(['checkpoints' => ['post']]), 'the post checkpoint filter was wrong');
	assertSameValue(1, $service->countBans(['checkpoints' => ['soudane']]), 'a secondary checkpoint was not matched');
	assertSameValue(2, $service->countBans(['checkpoints' => ['post', 'report']]), 'ticking two did not mean either');
	assertSameValue(0, $service->countBans(['checkpoints' => ['pm']]), 'a checkpoint nothing blocks matched');
});

testCase('the reason filter reaches the notice and the staff note', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.62');
	$service->fileBan('203.0.113.62', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'flooding', null, null, false, false, false, 'public notice', 'staff only');

	assertSameValue(1, $service->countBans(['reason' => 'flooding']), 'the reason was not searched');
	assertSameValue(1, $service->countBans(['reason' => 'public notice']), 'the public notice was not searched');
	assertSameValue(1, $service->countBans(['reason' => 'staff only']), 'the staff note was not searched');
	assertSameValue(0, $service->countBans(['reason' => '203.0.113.62']), 'the reason filter reached the address');
});

testCase('the ban id and post number filters pick out one row', function () use ($makeService, $makeTokenPost, $clearBans, $pdo, $tableNames): void {
	$clearBans();

	$service = $makeService('203.0.113.63');
	$postUid = $makeTokenPost('203.0.113.63', null);
	$postNumber = (int) $pdo->query("SELECT no FROM `{$tableNames['POST_TABLE']}` WHERE post_uid = {$postUid}")->fetchColumn();

	$banId = $service->fileBan('203.0.113.63', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'on a post', null, $postUid);
	$service->fileBan('203.0.113.64', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'on nothing', null);

	assertSameValue(1, $service->countBans(['banId' => $banId]), 'the ban id filter was wrong');
	assertSameValue(0, $service->countBans(['banId' => $banId + 1000]), 'an unknown ban id matched something');
	assertSameValue(1, $service->countBans(['postNumber' => $postNumber]), 'the post number filter was wrong');
	assertSameValue(0, $service->countBans(['postNumber' => $postNumber + 1000]), 'an unbanned post matched something');
});

testCase('the filed-on date range covers whole days', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.65');
	$service->fileBan('203.0.113.65', GLOBAL_BOARD_UID, ['post'], time() + 3600, 'today', null);

	$today = date('Y-m-d');
	$tomorrow = date('Y-m-d', time() + 86400);
	$yesterday = date('Y-m-d', time() - 86400);

	// A ban filed at any hour of today has to fall inside a range naming today at both ends.
	assertSameValue(1, $service->countBans(['dateAfter' => $today, 'dateBefore' => $today]), 'today did not include a ban filed today');
	assertSameValue(0, $service->countBans(['dateAfter' => $tomorrow]), 'a ban filed today matched a later range');
	assertSameValue(0, $service->countBans(['dateBefore' => $yesterday]), 'a ban filed today matched an earlier range');
	assertSameValue(1, $service->countBans(['dateAfter' => 'nonsense']), 'a junk date filtered anything at all');
});

testCase('listing is newest first and pages', function () use ($makeService, $clearBans): void {
	$clearBans();

	$service = $makeService('203.0.113.60');

	for ($i = 1; $i <= 5; $i++) {
		$service->fileBan("203.0.113.6{$i}", GLOBAL_BOARD_UID, ['post'], time() + 3600, "ban {$i}", null);
	}

	$firstPage = $service->listBans([], 2, 0);
	$secondPage = $service->listBans([], 2, 2);

	assertSameValue(2, count($firstPage), 'the page size was not honoured');
	assertSameValue(2, count($secondPage), 'the second page was the wrong size');

	$ids = array_map(static fn(banEntry $ban): int => $ban->id, array_merge($firstPage, $secondPage));

	assertSameValue($ids, array_values(array_unique($ids)), 'paging returned the same ban twice');

	$sorted = $ids;
	rsort($sorted);
	assertSameValue($sorted, $ids, 'the listing was not newest first');
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";

if ($failed !== []) {
	echo "\033[31m" . count($failed) . " failed\033[0m\n\n";

	foreach ($failed as $failure) {
		echo "  - {$failure}\n";
	}

	exit(1);
}

echo "\033[32m{$passed} passed\033[0m\n";
exit(0);
