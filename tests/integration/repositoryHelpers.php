<?php

/**
 * Integration tests for the shared repository layer, against a real MariaDB.
 *
 * baseRepository is where every repository's SQL is actually built, so its helpers are only
 * meaningfully testable against an engine: what a placeholder list means to the parser, what a
 * guarded UPDATE reports as affected, what shape a single-column SELECT comes back in.
 *
 * Each read helper is checked against the same result fetched with hand-written SQL, so a
 * refactor that changes the shape of what a repository returns fails here rather than in a
 * renderer three layers up.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/repositoryHelpers.php
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

// The libraries are namespaced *functions*, not autoloaded classes; the repositories under test
// call into lib_query's deletion-condition builders and lib_database's placeholder helper.
require_once $root . '/code/Kokonotsuba/libraries/lib_database.php';
require_once $root . '/code/Kokonotsuba/libraries/lib_query.php';

use Kokonotsuba\ban\banAppealRepository;
use Kokonotsuba\ban\banAppealStatus;
use Kokonotsuba\board\boardRepository;
use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\post\deletion\deletedPostsRepository;
use Kokonotsuba\post\postRepository;
use Kokonotsuba\quote_link\quoteLinkRepository;
use Kokonotsuba\thread\threadRepository;

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

$dsn = getenv('KOKO_TEST_DSN') ?: '';
$user = getenv('KOKO_TEST_USER') ?: '';
$pass = getenv('KOKO_TEST_PASS') ?: '';

if ($dsn === '') {
	fwrite(STDERR, "Set KOKO_TEST_DSN / KOKO_TEST_USER / KOKO_TEST_PASS to a scratch database.\n");
	exit(2);
}

if (!preg_match('/host=([^;]+)/', $dsn, $hostMatch) || !preg_match('/dbname=([^;]+)/', $dsn, $nameMatch)) {
	fwrite(STDERR, "KOKO_TEST_DSN must contain host= and dbname=.\n");
	exit(2);
}

preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);

try {
	$pdo = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
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

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

$dropAll = static function () use ($pdo): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
		$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	}
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$dropAll();

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
// Fixtures
// ---------------------------------------------------------------------------

$pdo->exec("INSERT INTO `{$tableNames['ACCOUNT_TABLE']}` (username, role, password_hash) VALUES ('Mod', 4, 'x')");
$accountId = (int)$pdo->lastInsertId();

$pdo->exec(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name, listed)
	 VALUES (1, 'a', 'Board A', 'a', 1), (2, 'b', 'Board B', 'b', 1), (3, 'c', 'Board C', 'c', 0)"
);

/** Three threads on board 1, one of them stickied; UIDs are deliberately non-numeric strings. */
$threadUids = ['t-alpha', 't-beta', 't-gamma'];

$insertPost = $pdo->prepare(
	"INSERT INTO `{$tableNames['POST_TABLE']}`
	 (post_uid, no, boardUID, thread_uid, post_position, is_op, root, pwd, now, name, email, sub, com, host, status)
	 VALUES (:uid, :no, :board, :thread, :pos, :is_op, :root, '', '', 'Anonymous', '', '', :com, '10.0.0.1', '')"
);

$insertThread = $pdo->prepare(
	"INSERT INTO `{$tableNames['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID, is_sticky)
	 VALUES (:thread, :no, :uid, :board, :sticky)"
);

$postUid = 0;
foreach ($threadUids as $index => $threadUid) {
	// The thread row comes first: posts.thread_uid is a foreign key onto it.
	$opUid = ++$postUid;
	$insertThread->execute([
		':thread' => $threadUid, ':no' => 100 + $index, ':uid' => $opUid,
		':board' => 1, ':sticky' => $index === 1 ? 1 : 0,
	]);
	$insertPost->execute([
		':uid' => $opUid, ':no' => 100 + $index, ':board' => 1, ':thread' => $threadUid,
		':pos' => 0, ':is_op' => 1, ':root' => '2026-01-0' . ($index + 1) . ' 00:00:00',
		':com' => 'op ' . $index,
	]);

	// two replies per thread
	for ($reply = 1; $reply <= 2; $reply++) {
		$insertPost->execute([
			':uid' => ++$postUid, ':no' => 200 + ($index * 10) + $reply, ':board' => 1, ':thread' => $threadUid,
			':pos' => $reply, ':is_op' => 0, ':root' => '2026-01-0' . ($index + 1) . ' 0' . $reply . ':00:00',
			':com' => 'reply ' . $index . '-' . $reply,
		]);
	}
}

// A pair of posts sharing a comment, for the repeated-post spam lookup.
$insertPost->execute([
	':uid' => ++$postUid, ':no' => 900, ':board' => 1, ':thread' => 't-alpha',
	':pos' => 3, ':is_op' => 0, ':root' => gmdate('Y-m-d H:i:s'), ':com' => 'buy pills',
]);
$repeatedA = $postUid;
$insertPost->execute([
	':uid' => ++$postUid, ':no' => 901, ':board' => 1, ':thread' => 't-beta',
	':pos' => 3, ':is_op' => 0, ':root' => gmdate('Y-m-d H:i:s'), ':com' => 'buy pills',
]);
$repeatedB = $postUid;

// ---------------------------------------------------------------------------
// Repositories
// ---------------------------------------------------------------------------

/** Exposes baseRepository's protected helpers so they can be exercised directly. */
final class helperProbe extends baseRepository {
	public function flatColumn(string $sql, array $params = [], int $column = 0): array {
		return $this->queryFlatColumn($sql, $params, $column);
	}

	public function affected(string $sql, array $params = []): int {
		return $this->queryAffected($sql, $params);
	}

	public function inClause(array $values): string {
		return $this->buildInClause($values);
	}

	public function direction(string $direction, string $default = 'DESC'): string {
		return self::sortDirection($direction, $default);
	}

	public function flatPluckAll(string $select, string $where, mixed $value): array {
		return $this->pluckAll($select, $where, $value);
	}

	public function flatPluckWhereIn(string $select, string $where, array $values, bool $distinct = false): array {
		return $this->pluckWhereIn($select, $where, $values, $distinct);
	}
}

$probe = new helperProbe($databaseConnection, $tableNames['POST_TABLE']);

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

$posts = new postRepository(
	$databaseConnection,
	$tableNames['POST_TABLE'],
	$tableNames['THREAD_TABLE'],
	$tableNames['DELETED_POSTS_TABLE'],
	$tableNames['FILE_TABLE'],
	$tableNames['SOUDANE_TABLE'],
	$tableNames['NOTE_TABLE'],
	$tableNames['ACCOUNT_TABLE'],
);

$boards = new boardRepository($databaseConnection, $tableNames['BOARD_TABLE']);

$quoteLinks = new quoteLinkRepository(
	$databaseConnection,
	$tableNames['QUOTE_LINK_TABLE'],
	$tableNames['POST_TABLE'],
	$tableNames['THREAD_TABLE'],
	$tableNames['DELETED_POSTS_TABLE'],
);

$deletedPosts = new deletedPostsRepository(
	$databaseConnection,
	$tableNames['DELETED_POSTS_TABLE'],
	$tableNames['POST_TABLE'],
	$tableNames['ACCOUNT_TABLE'],
	$tableNames['FILE_TABLE'],
	$tableNames['THREAD_TABLE'],
	$tableNames['SOUDANE_TABLE'],
	$tableNames['NOTE_TABLE'],
);

$appeals = new banAppealRepository(
	$databaseConnection,
	$tableNames['BAN_APPEAL_TABLE'],
	$tableNames['BAN_TABLE'],
	$tableNames['ACCOUNT_TABLE'],
	$tableNames['BOARD_TABLE'],
);

require_once $root . '/module/sticky/stickyRepository.php';
require_once $root . '/module/banner/bannerEntry.php';
require_once $root . '/module/banner/bannerRepository.php';

$sticky = new Kokonotsuba\Modules\sticky\stickyRepository($databaseConnection, $tableNames['THREAD_TABLE']);
$banners = new Kokonotsuba\Modules\banner\bannerRepository($databaseConnection, $tableNames['BANNER_AD_TABLE']);

/** Raw comparison query, bypassing the repository layer entirely. */
$raw = static function (string $sql, array $params = []) use ($pdo): array {
	$statement = $pdo->prepare($sql);
	$statement->execute($params);
	return $statement->fetchAll(PDO::FETCH_COLUMN);
};

// ---------------------------------------------------------------------------

echo "\nbaseRepository helpers\n";

testCase('queryFlatColumn returns one flat list, not a list of rows', function () use ($probe, $tableNames, $raw) {
	$sql = "SELECT post_uid FROM `{$tableNames['POST_TABLE']}` WHERE thread_uid = 't-alpha' ORDER BY post_uid";

	$actual = $probe->flatColumn($sql);

	assertSameValue(array_map('intval', $raw($sql)), $actual, 'the column did not come back flat');
	assertTrueValue(array_is_list($actual), 'the result was not a list');
});

testCase('queryFlatColumn returns an empty array when nothing matches', function () use ($probe, $tableNames) {
	$flat = $probe->flatColumn("SELECT post_uid FROM `{$tableNames['POST_TABLE']}` WHERE thread_uid = 'nope'");

	assertSameValue([], $flat, 'an empty result set did not come back as an empty array');
});

testCase('queryFlatColumn can take a column other than the first', function () use ($probe, $tableNames) {
	$flat = $probe->flatColumn(
		"SELECT post_uid, no FROM `{$tableNames['POST_TABLE']}` WHERE thread_uid = 't-alpha' AND is_op = 1",
		[],
		1
	);

	assertSameValue([100], $flat, 'the second column was not returned');
});

testCase('queryAffected counts the rows a multi-row insert wrote', function () use ($probe, $tableNames, $pdo) {
	$table = $tableNames['QUOTE_LINK_TABLE'];
	$pdo->exec("DELETE FROM `{$table}`");

	$written = $probe->affected(
		"INSERT INTO `{$table}` (board_uid, host_post_uid, target_post_uid) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)",
		[1, 2, 1, 1, 3, 1, 1, 5, 4]
	);

	assertSameValue(3, $written, 'the insert reported the wrong row count');
	$pdo->exec("DELETE FROM `{$table}`");
});

testCase('queryAffected counts only the rows an update actually changed', function () use ($probe, $tableNames, $pdo) {
	$table = $tableNames['THREAD_TABLE'];
	$pdo->exec("UPDATE `{$table}` SET is_sticky = 0");

	// two of the three match the guard, so a caller cannot just count the ids it passed
	$pdo->exec("UPDATE `{$table}` SET is_sticky = 1 WHERE thread_uid = 't-alpha'");

	$changed = $probe->affected(
		"UPDATE `{$table}` SET is_sticky = 1 WHERE thread_uid IN (?, ?, ?) AND is_sticky = 0",
		['t-alpha', 't-beta', 't-gamma']
	);

	assertSameValue(2, $changed, 'the guarded update reported the wrong row count');

	$pdo->exec("UPDATE `{$table}` SET is_sticky = 0");
	$pdo->exec("UPDATE `{$table}` SET is_sticky = 1 WHERE thread_uid = 't-beta'");
});

testCase('buildInClause brings its own parentheses', function () use ($probe) {
	assertSameValue('(?)', $probe->inClause([1]), 'a single value was not wrapped');
	assertSameValue('(?, ?, ?)', $probe->inClause([1, 2, 3]), 'a list was not wrapped');
	assertSameValue('(NULL)', $probe->inClause([]), 'an empty list did not degrade to (NULL)');
});

testCase('an in-clause of any length parses and matches', function () use ($probe, $tableNames) {
	$table = $tableNames['THREAD_TABLE'];

	foreach ([['t-alpha'], ['t-alpha', 't-beta'], ['t-alpha', 't-beta', 't-gamma']] as $uids) {
		$clause = $probe->inClause($uids);
		$found = $probe->flatColumn("SELECT thread_uid FROM `{$table}` WHERE thread_uid IN {$clause}", $uids);

		sort($found);
		assertSameValue($uids, $found, 'an IN list of ' . count($uids) . ' did not match its own values');
	}
});

testCase('an empty in-clause matches nothing rather than erroring', function () use ($probe, $tableNames) {
	$clause = $probe->inClause([]);
	$found = $probe->flatColumn("SELECT thread_uid FROM `{$tableNames['THREAD_TABLE']}` WHERE thread_uid IN {$clause}");

	assertSameValue([], $found, 'an empty IN list did not match nothing');
});

testCase('sortDirection folds anything unrecognised to the default', function () use ($probe) {
	assertSameValue('ASC', $probe->direction('asc'), 'lowercase ASC was not accepted');
	assertSameValue('DESC', $probe->direction('DeSc'), 'mixed-case DESC was not accepted');
	assertSameValue('DESC', $probe->direction('; DROP TABLE posts'), 'junk was not folded to the default');
	assertSameValue('ASC', $probe->direction('', 'ASC'), 'the supplied default was ignored');
	assertSameValue('DESC', $probe->direction('nonsense', 'sideways'), 'a junk default did not fall back to DESC');
});

testCase('pluckAll and pluckWhereIn return flat lists', function () use ($probe, $tableNames, $raw) {
	$table = $tableNames['POST_TABLE'];

	$all = $probe->flatPluckAll('post_uid', 'thread_uid', 't-alpha');
	$expected = array_map('intval', $raw("SELECT post_uid FROM `{$table}` WHERE thread_uid = 't-alpha'"));
	assertSameValue($expected, $all, 'pluckAll did not match raw SQL');

	$in = $probe->flatPluckWhereIn('no', 'thread_uid', ['t-beta', 't-gamma']);
	$expectedIn = array_map('intval', $raw("SELECT no FROM `{$table}` WHERE thread_uid IN ('t-beta', 't-gamma')"));
	assertSameValue($expectedIn, $in, 'pluckWhereIn did not match raw SQL');

	assertSameValue([], $probe->flatPluckWhereIn('no', 'thread_uid', []), 'an empty IN list did not short-circuit');
});

testCase('pluckWhereIn deduplicates when asked to', function () use ($probe) {
	$boards = $probe->flatPluckWhereIn('boardUID', 'thread_uid', ['t-alpha', 't-beta'], true);

	assertSameValue([1], $boards, 'DISTINCT did not collapse the repeated board');
});

echo "\nIN lists in concrete repositories\n";

testCase('mapThreadUidListToPostNumber handles a list of more than one UID', function () use ($threads) {
	// Regression: the clause helper already carries its parentheses, so wrapping it a second
	// time produced "IN ((?, ?))" - a row constructor, which MariaDB rejects outright.
	$rows = $threads->mapThreadUidListToPostNumber(['t-alpha', 't-beta', 't-gamma']);

	$numbers = array_map(static fn(array $row): int => (int)$row['post_op_number'], $rows);
	sort($numbers);

	assertSameValue([100, 101, 102], $numbers, 'a multi-UID lookup did not return every thread');
});

testCase('mapThreadUidListToPostNumber still handles one UID and none', function () use ($threads) {
	$one = $threads->mapThreadUidListToPostNumber(['t-beta']);

	assertSameValue(1, count($one), 'a single-UID lookup returned the wrong number of rows');
	assertSameValue(101, (int)$one[0]['post_op_number'], 'a single-UID lookup returned the wrong thread');
	assertSameValue([], $threads->mapThreadUidListToPostNumber([]), 'an empty list did not short-circuit');
});

testCase('fetchThreadUIDsByBoard returns a flat list matching raw SQL', function () use ($threads, $tableNames, $raw) {
	$actual = $threads->fetchThreadUIDsByBoard('1', 0, 0, 'last_bump_time', 'ASC');

	$expected = $raw(
		"SELECT t.thread_uid FROM `{$tableNames['THREAD_TABLE']}` t WHERE t.boardUID = 1 ORDER BY last_bump_time ASC"
	);

	assertSameValue($expected, $actual, 'the thread UID list did not match raw SQL');
	assertTrueValue(array_is_list($actual), 'the thread UID list was not flat');
});

testCase('fetchThreadUIDsByBoard falls back on a junk direction', function () use ($threads) {
	$rows = $threads->fetchThreadUIDsByBoard('1', 0, 0, 'last_bump_time', 'sideways');

	assertSameValue(3, count($rows), 'a junk direction did not fall through to the default ordering');
});

testCase('fetchThreadUIDsByBoard returns an empty list for a board with no threads', function () use ($threads) {
	assertSameValue([], $threads->fetchThreadUIDsByBoard('2'), 'an empty board did not return an empty list');
});

testCase('getAllBoardUIDs returns every board UID as a flat list', function () use ($boards, $tableNames, $raw) {
	$actual = $boards->getAllBoardUIDs();
	$expected = array_map('intval', $raw("SELECT board_uid FROM `{$tableNames['BOARD_TABLE']}`"));

	assertSameValue($expected, $actual, 'the board UID list did not match raw SQL');
});

testCase('getStickyThreadUids returns only the stickied threads', function () use ($sticky) {
	$stickied = $sticky->getStickyThreadUids(['t-alpha', 't-beta', 't-gamma']);

	assertSameValue(['t-beta'], $stickied, 'the wrong threads came back as sticky');
	assertSameValue([], $sticky->getStickyThreadUids([]), 'an empty list did not short-circuit');
	assertSameValue([], $sticky->getStickyThreadUids(['t-alpha']), 'a non-sticky thread was reported as sticky');
});

testCase('getRepeatedPosts finds every post sharing a comment', function () use ($posts, $repeatedA, $repeatedB) {
	$found = $posts->getRepeatedPosts('buy pills', null, 3600);

	sort($found);
	assertSameValue([$repeatedA, $repeatedB], array_map('intval', $found), 'the repeated posts were not both found');
	assertSameValue(null, $posts->getRepeatedPosts('nothing like this', null, 3600), 'no match did not come back as null');
});

testCase('getRepeatedPosts honours the excluded default comment', function () use ($posts) {
	assertSameValue(
		null,
		$posts->getRepeatedPosts('buy pills', 'buy pills', 3600),
		'the excluded boilerplate comment still matched'
	);
});

testCase('findThreadNumbersForPostNumbers maps each number to its thread', function () use ($posts) {
	// 100/101 are OPs, 201 and 212 are replies in the first and second thread
	$found = $posts->findThreadNumbersForPostNumbers([1 => [100, 201, 212, 999]]);

	assertSameValue(
		[100 => 100, 201 => 100, 212 => 101],
		$found[1] ?? [],
		'post numbers did not map onto their threads'
	);
});

testCase('findThreadNumbersForPostNumbers keeps the boards apart', function () use ($posts) {
	// board 2 holds none of these numbers, so asking for them there must find nothing
	$found = $posts->findThreadNumbersForPostNumbers([2 => [100, 201]]);

	assertSameValue([], $found, 'numbers were matched on the wrong board');
	assertSameValue([], $posts->findThreadNumbersForPostNumbers([]), 'an empty request queried anyway');
});

testCase('insertPostsBatch writes every row, not just the last', function () use ($posts, $tableNames, $pdo) {
	// Each param array carries the same ':name' keys, so a batch built from them has to be bound
	// positionally - reusing the names made every row share one placeholder set.
	$make = static function (int $no, string $threadUid, string $comment): array {
		return [
			':no' => $no, ':poster_hash' => 'h', ':boardUID' => 1, ':thread_uid' => $threadUid,
			':post_position' => 9, ':is_op' => 0, ':root' => '2026-02-01 00:00:00', ':category' => '',
			':tag' => null, ':pwd' => '', ':now' => '', ':name' => 'Anonymous', ':tripcode' => null,
			':secure_tripcode' => null, ':capcode' => null, ':email' => '', ':sub' => '',
			':com' => $comment, ':host' => '10.0.0.5', ':visitor_token_hash' => null,
			':status' => '', ':text_format' => 1,
		];
	};

	$uids = $posts->insertPostsBatch([
		$make(1001, 't-alpha', 'batched one'),
		$make(1002, 't-beta', 'batched two'),
		$make(1003, 't-gamma', 'batched three'),
	]);

	assertSameValue(3, count($uids), 'the returned UID range was the wrong length');

	$statement = $pdo->prepare(
		"SELECT no, thread_uid, com, text_format FROM `{$tableNames['POST_TABLE']}` WHERE post_uid IN (?, ?, ?) ORDER BY post_uid"
	);
	$statement->execute($uids);
	$stored = $statement->fetchAll(PDO::FETCH_ASSOC);

	assertSameValue(3, count($stored), 'not every row in the batch was written');
	assertSameValue([1001, 1002, 1003], array_map(static fn($r) => (int)$r['no'], $stored), 'the rows lost their own values');
	assertSameValue(
		['t-alpha', 't-beta', 't-gamma'],
		array_column($stored, 'thread_uid'),
		'the rows were not written against their own threads'
	);
	assertSameValue(
		['batched one', 'batched two', 'batched three'],
		array_column($stored, 'com'),
		'the rows did not keep their own comments'
	);
	assertSameValue(
		['1', '1', '1'],
		array_map('strval', array_column($stored, 'text_format')),
		'the batch did not carry each row\'s text format'
	);

	$pdo->prepare("DELETE FROM `{$tableNames['POST_TABLE']}` WHERE post_uid IN (?, ?, ?)")->execute($uids);
});

testCase('insertPostsBatch short-circuits on an empty batch', function () use ($posts) {
	assertSameValue([], $posts->insertPostsBatch([]), 'an empty batch did not short-circuit');
});

echo "\nRow counts reported by writes\n";

testCase('insertQuoteLinks reports how many links it wrote', function () use ($quoteLinks, $tableNames, $pdo) {
	$pdo->exec("DELETE FROM `{$tableNames['QUOTE_LINK_TABLE']}`");

	$written = $quoteLinks->insertQuoteLinks([
		['board_uid' => 1, 'host_post_uid' => 2, 'target_post_uid' => 1],
		['board_uid' => 1, 'host_post_uid' => 3, 'target_post_uid' => 1],
		['board_uid' => 1, 'host_post_uid' => 5, 'target_post_uid' => 4],
	]);

	assertSameValue(3, $written, 'the insert did not report the number of rows written');

	$stored = (int)$pdo->query("SELECT COUNT(*) FROM `{$tableNames['QUOTE_LINK_TABLE']}`")->fetchColumn();
	assertSameValue(3, $stored, 'the reported count did not match what was stored');
});

testCase('insertQuoteLinks reports zero when it has nothing valid to write', function () use ($quoteLinks) {
	assertSameValue(0, $quoteLinks->insertQuoteLinks([]), 'an empty batch did not report zero');
	assertSameValue(
		0,
		$quoteLinks->insertQuoteLinks([['board_uid' => 'x', 'host_post_uid' => null, 'target_post_uid' => 'y']]),
		'a batch of malformed links did not report zero'
	);
});

testCase('insertQuoteLinks skips malformed links in a mixed batch', function () use ($quoteLinks, $tableNames, $pdo) {
	$pdo->exec("DELETE FROM `{$tableNames['QUOTE_LINK_TABLE']}`");

	$written = $quoteLinks->insertQuoteLinks([
		['board_uid' => 1, 'host_post_uid' => 2, 'target_post_uid' => 1],
		['board_uid' => 1, 'host_post_uid' => 'nonsense', 'target_post_uid' => 1],
		['board_uid' => 1, 'host_post_uid' => 3, 'target_post_uid' => 1],
	]);

	assertSameValue(2, $written, 'the skipped link was still counted');
	$pdo->exec("DELETE FROM `{$tableNames['QUOTE_LINK_TABLE']}`");
});

testCase('decideAppeals reports only the appeals it actually closed', function () use ($appeals, $tableNames, $pdo, $accountId) {
	$pdo->exec("DELETE FROM `{$tableNames['BAN_APPEAL_TABLE']}`");
	$pdo->exec("DELETE FROM `{$tableNames['BAN_TABLE']}`");

	$pdo->exec(
		"INSERT INTO `{$tableNames['BAN_TABLE']}` (ban_id, board_uid, ip_pattern, reason)
		 VALUES (1, 1, '10.0.0.1', 'spam')"
	);

	$insert = $pdo->prepare(
		"INSERT INTO `{$tableNames['BAN_APPEAL_TABLE']}` (appeal_id, ban_id, appellant_ip, reason, status)
		 VALUES (:id, 1, '10.0.0.1', 'sorry', :status)"
	);
	$insert->execute([':id' => 1, ':status' => banAppealStatus::PENDING->value]);
	$insert->execute([':id' => 2, ':status' => banAppealStatus::PENDING->value]);
	// already handled by a colleague, so it must not be counted or overwritten
	$insert->execute([':id' => 3, ':status' => banAppealStatus::DENIED->value]);

	$closed = $appeals->decideAppeals([1, 2, 3], banAppealStatus::APPROVED, $accountId, 'ok');

	assertSameValue(2, $closed, 'the already-actioned appeal was counted as closed');

	$statuses = $pdo->query(
		"SELECT appeal_id, status FROM `{$tableNames['BAN_APPEAL_TABLE']}` ORDER BY appeal_id"
	)->fetchAll(PDO::FETCH_KEY_PAIR);

	assertSameValue(banAppealStatus::APPROVED->value, (int)$statuses[1], 'a pending appeal was not approved');
	assertSameValue(banAppealStatus::APPROVED->value, (int)$statuses[2], 'a pending appeal was not approved');
	assertSameValue(banAppealStatus::DENIED->value, (int)$statuses[3], 'an actioned appeal was overwritten');
});

testCase('decideAppeals reports zero when everything was already handled', function () use ($appeals, $accountId) {
	assertSameValue(0, $appeals->decideAppeals([1, 2, 3], banAppealStatus::DENIED, $accountId, 'x'), 'a no-op reported work');
	assertSameValue(0, $appeals->decideAppeals([], banAppealStatus::DENIED, $accountId, 'x'), 'an empty list reported work');
});

echo "\nDeleted-post paging\n";

testCase('getExpiredEntryIDs returns a flat list of ids', function () use ($deletedPosts, $tableNames, $pdo, $accountId, $raw) {
	$table = $tableNames['DELETED_POSTS_TABLE'];
	$pdo->exec("DELETE FROM `{$table}`");

	$pdo->exec(
		"INSERT INTO `{$table}` (post_uid, deleted_by, deleted_at, file_only, by_proxy)
		 VALUES (2, {$accountId}, NOW() - INTERVAL 10 DAY, 0, 0),
		        (3, {$accountId}, NOW() - INTERVAL 10 DAY, 0, 0),
		        (5, {$accountId}, NOW(), 0, 0)"
	);

	$expired = $deletedPosts->getExpiredEntryIDs(24);
	$expected = array_map('intval', $raw(
		"SELECT id FROM `{$table}` WHERE deleted_at < NOW() - INTERVAL 24 HOUR AND open_flag = 1 AND file_only = 0"
	));

	assertSameValue($expected, array_map('intval', $expired), 'the expired ids did not match raw SQL');
	assertSameValue(2, count($expired), 'the wrong number of entries expired');
});

testCase('getExpiredEntryIDs returns an empty list when nothing has expired', function () use ($deletedPosts) {
	assertSameValue([], $deletedPosts->getExpiredEntryIDs(24 * 365), 'nothing expired but a list came back');
});

testCase('getPagedEntries pages over post_uids and reports false when empty', function () use ($deletedPosts, $tableNames, $pdo) {
	$page = $deletedPosts->getPagedEntries(10, 0, 'id', 'DESC');

	assertTrueValue(is_array($page), 'a populated page did not come back as an array');
	assertSameValue(3, count($page), 'the page did not carry every open deletion');

	assertSameValue(
		false,
		$deletedPosts->getPagedEntries(10, 500, 'id', 'DESC'),
		'an offset past the end did not report false'
	);

	$pdo->exec("DELETE FROM `{$tableNames['DELETED_POSTS_TABLE']}`");
	assertSameValue(false, $deletedPosts->getPagedEntries(10, 0, 'id', 'DESC'), 'an empty table did not report false');
});

testCase('getPagedEntries tolerates a junk sort direction', function () use ($deletedPosts, $tableNames, $pdo, $accountId) {
	$pdo->exec(
		"INSERT INTO `{$tableNames['DELETED_POSTS_TABLE']}` (post_uid, deleted_by, file_only, by_proxy)
		 VALUES (2, {$accountId}, 0, 0)"
	);

	$page = $deletedPosts->getPagedEntries(10, 0, 'id', 'sideways');

	assertSameValue(1, count($page ?: []), 'a junk direction did not fall through to the default ordering');
	$pdo->exec("DELETE FROM `{$tableNames['DELETED_POSTS_TABLE']}`");
});

echo "\nBanner repository routed through baseRepository\n";

testCase('banner counts and paging match raw SQL', function () use ($banners, $tableNames, $pdo) {
	$table = $tableNames['BANNER_AD_TABLE'];
	$pdo->exec("DELETE FROM `{$table}`");
	$pdo->exec(
		"INSERT INTO `{$table}` (banner_file_name, preset, link, ip_address, is_active, is_approved, date_submitted)
		 VALUES ('a.png', 'ad', NULL, '10.0.0.1', 1, 1, '2026-01-01 00:00:00'),
		        ('b.png', 'ad', NULL, '10.0.0.1', 1, 1, '2026-01-02 00:00:00'),
		        ('c.png', 'ad', NULL, '10.0.0.2', 0, 1, '2026-01-03 00:00:00'),
		        ('d.png', 'ad', NULL, '10.0.0.2', 1, 0, '2026-01-04 00:00:00'),
		        ('e.png', 'board', NULL, '10.0.0.3', 1, 1, '2026-01-05 00:00:00')"
	);

	assertSameValue(5, $banners->countAll(null), 'the total banner count was wrong');
	assertSameValue(4, $banners->countAll('ad'), 'the per-preset total was wrong');
	assertSameValue(2, $banners->countApprovedActive('ad'), 'the approved+active count was wrong');
	assertSameValue(1, $banners->countApprovedActive('board'), 'the other preset leaked into the count');
	assertSameValue(1, $banners->countPending(), 'the pending count was wrong');

	$all = $banners->getAllPaginated(null, 100);
	assertSameValue(
		['e.png', 'd.png', 'c.png', 'b.png', 'a.png'],
		array_map(static fn($entry): string => $entry->banner_file_name, $all),
		'getAllPaginated lost its newest-first ordering'
	);

	$page = $banners->getAllPaginated('ad', 2, 1);
	assertSameValue(
		['c.png', 'b.png'],
		array_map(static fn($entry): string => $entry->banner_file_name, $page),
		'the paginated slice was wrong'
	);

	$active = $banners->getApprovedActivePaginated('ad', 1, 0);
	assertSameValue(1, count($active), 'the approved+active page was the wrong size');
	assertSameValue('b.png', $active[0]->banner_file_name, 'the approved+active page started at the wrong row');
});

testCase('getLastSubmissionTimeForIp is scoped to one preset', function () use ($banners) {
	assertSameValue('2026-01-02 00:00:00', $banners->getLastSubmissionTimeForIp('ad', '10.0.0.1'), 'the wrong submission time came back');
	assertSameValue(null, $banners->getLastSubmissionTimeForIp('board', '10.0.0.1'), 'another preset\'s submission was counted');
	assertSameValue(null, $banners->getLastSubmissionTimeForIp('ad', '10.9.9.9'), 'an unknown IP did not return null');
});

testCase('a random active banner is always an approved active one of that preset', function () use ($banners) {
	for ($attempt = 0; $attempt < 8; $attempt++) {
		$entry = $banners->getRandomActive('ad');

		assertTrueValue($entry !== null, 'no banner came back');
		assertTrueValue(in_array($entry->banner_file_name, ['a.png', 'b.png'], true), 'an inactive, unapproved or off-preset banner was picked');
	}

	assertSameValue('e.png', $banners->getRandomActive('board')->banner_file_name, 'the board preset picked the wrong row');
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$dropAll();

echo "\n";

if ($failed !== []) {
	echo "\033[31mFailures:\033[0m\n";
	foreach ($failed as $failure) {
		echo "  - {$failure}\n";
	}
	echo "\n{$passed} passed, " . count($failed) . " failed\n";
	exit(1);
}

echo "\033[32m{$passed} passed\033[0m\n";
exit(0);
