<?php

/**
 * Integration tests for post-deletion visibility, run against a real MariaDB.
 *
 * The unit suite pins the *shape* of the generated SQL. This pins what that SQL actually means once
 * the database evaluates it — which is the part that matters, because the whole predicate rests on
 * a STORED generated column and its UNIQUE index:
 *
 *     open_key = CASE WHEN restored_at IS NULL AND file_id IS NULL THEN post_uid ELSE NULL END
 *     UNIQUE KEY uq_open_post (open_key)
 *
 * Repeated NULLs are permitted in a MySQL unique index, so a post keeps its full delete/restore
 * history while the database still guarantees at most one *open post-level* row per post. The
 * queries rely on that guarantee instead of re-deriving each post's current state at read time.
 *
 * The schema below is a faithful cut-down of install.php: same generated columns, same unique key,
 * same cascade, minus the columns and indexes these queries never touch.
 *
 * Usage — point this at a throwaway database; it drops and recreates its tables on every run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_predicate_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=koko_test KOKO_TEST_PASS=kokotest \
 *   php tests/integration/deletionSemantics.php
 *
 * Exit code is 0 when everything passes, 1 on failure, 2 when no database is reachable.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

require_once __DIR__ . '/../../code/Kokonotsuba/libraries/lib_query.php';

use function Kokonotsuba\libraries\excludeDeletedThreadsCondition;
use function Kokonotsuba\libraries\getBasePostQuery;
use function Kokonotsuba\libraries\objectivePositionSubquery;
use function Kokonotsuba\libraries\openDeletionExistsCondition;
use function Kokonotsuba\libraries\openFileDeletionExistsCondition;

const POSTS = 'posts';
const DELETED = 'deleted_posts';
const FILES = 'files';
const THREADS = 'threads';
const SOUDANE = 'soudane_votes';
const NOTES = 'notes';
const ACCOUNTS = 'accounts';

// ---------------------------------------------------------------------------
// Tiny runner
// ---------------------------------------------------------------------------

$passed = 0;
$failed = [];

/** Register and immediately run a case. */
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

function assertVisible(array $uids, int $uid, string $message): void {
	if (!in_array($uid, $uids, true)) {
		throw new RuntimeException($message . "\n  post {$uid} should be visible; visible set: " . implode(',', $uids));
	}
}

function assertHidden(array $uids, int $uid, string $message): void {
	if (in_array($uid, $uids, true)) {
		throw new RuntimeException($message . "\n  post {$uid} should be hidden; visible set: " . implode(',', $uids));
	}
}

// ---------------------------------------------------------------------------
// Connection
// ---------------------------------------------------------------------------

$dsn = getenv('KOKO_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=koko_predicate_test;charset=utf8mb4';
$user = getenv('KOKO_TEST_USER') ?: 'koko_test';
$pass = getenv('KOKO_TEST_PASS') ?: 'kokotest';

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
	fwrite(STDERR, "pdo_mysql is not installed; cannot run the integration suite.\n");
	exit(2);
}

try {
	$pdo = new PDO($dsn, $user, $pass, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
} catch (PDOException $e) {
	fwrite(STDERR, "Cannot reach the test database: {$e->getMessage()}\n");
	fwrite(STDERR, "Create one with:\n");
	fwrite(STDERR, "  sudo mysql -e \"CREATE DATABASE IF NOT EXISTS koko_predicate_test;"
		. " CREATE USER IF NOT EXISTS 'koko_test'@'localhost' IDENTIFIED BY 'kokotest';"
		. " GRANT ALL PRIVILEGES ON \\`koko_predicate_test\\`.* TO 'koko_test'@'localhost';\"\n");
	exit(2);
}

// ---------------------------------------------------------------------------
// Schema + fixtures
// ---------------------------------------------------------------------------

/**
 * Rebuild the schema from scratch.
 *
 * Only this script's own tables are dropped, and constraint checking is off while they go: a
 * table left behind by another integration script (the ban tables reference posts and accounts)
 * would otherwise block the drop depending on which script ran last.
 */
function resetSchema(PDO $pdo): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

	foreach ([SOUDANE, NOTES, DELETED, FILES, POSTS, THREADS, ACCOUNTS] as $table) {
		$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	}

	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

	$pdo->exec("CREATE TABLE `" . THREADS . "` (
		insert_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		thread_uid VARCHAR(255) NOT NULL,
		post_op_number INT NOT NULL,
		post_op_post_uid INT NOT NULL,
		boardUID INT NOT NULL,
		last_bump_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		is_sticky BOOL DEFAULT FALSE,
		UNIQUE KEY uq_thread_uid (thread_uid)
	) ENGINE=InnoDB");

	$pdo->exec("CREATE TABLE `" . POSTS . "` (
		post_uid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		no INT NOT NULL,
		boardUID INT NOT NULL,
		thread_uid VARCHAR(255) NOT NULL,
		post_position INT DEFAULT 0,
		is_op BOOLEAN NOT NULL,
		root TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		pwd TEXT NOT NULL, now TEXT NOT NULL, name TEXT NOT NULL,
		email TEXT NOT NULL, sub TEXT NOT NULL, com MEDIUMTEXT NOT NULL,
		host VARCHAR(45) NOT NULL DEFAULT '',
		CONSTRAINT fk_t FOREIGN KEY (thread_uid) REFERENCES `" . THREADS . "`(thread_uid) ON DELETE CASCADE,
		INDEX (thread_uid)
	) ENGINE=InnoDB");

	$pdo->exec("CREATE TABLE `" . ACCOUNTS . "` (
		id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(255) NOT NULL
	) ENGINE=InnoDB");

	$pdo->exec("CREATE TABLE `" . FILES . "` (
		id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		post_uid INT NOT NULL,
		file_name VARCHAR(255) NOT NULL, stored_filename TEXT NOT NULL,
		file_ext VARCHAR(16) NOT NULL, file_md5 VARCHAR(32) NOT NULL,
		file_width INT NULL, file_height INT NULL,
		thumb_file_width INT NULL, thumb_file_height INT NULL,
		file_size BIGINT UNSIGNED NULL, mime_type VARCHAR(255) NULL,
		is_hidden TINYINT(1) NOT NULL DEFAULT 0, is_deleted TINYINT(1) NOT NULL DEFAULT 0,
		is_animated TINYINT(1) NOT NULL DEFAULT 0, is_spoilered TINYINT(1) NOT NULL DEFAULT 0,
		timestamp_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
		CONSTRAINT fk_f FOREIGN KEY (post_uid) REFERENCES `" . POSTS . "`(post_uid) ON DELETE CASCADE,
		INDEX idx_post_uid (post_uid)
	) ENGINE=InnoDB");

	// The columns under test, copied verbatim from install.php.
	$pdo->exec("CREATE TABLE `" . DELETED . "` (
		id INT AUTO_INCREMENT PRIMARY KEY,
		post_uid INT NOT NULL,
		deleted_by INT NULL,
		deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		file_only TINYINT(1) DEFAULT 0,
		by_proxy TINYINT(1) DEFAULT 0,
		restored_at TIMESTAMP NULL,
		restored_by INT NULL,
		file_id INT NULL,
		open_flag TINYINT(1) AS (IF(restored_at IS NULL, 1, 0)) STORED,
		open_key INT AS (CASE WHEN restored_at IS NULL AND file_id IS NULL THEN post_uid ELSE NULL END) STORED,
		CONSTRAINT fk_dp_post FOREIGN KEY (post_uid) REFERENCES `" . POSTS . "`(post_uid) ON DELETE CASCADE,
		CONSTRAINT fk_dp_file FOREIGN KEY (file_id) REFERENCES `" . FILES . "`(id) ON DELETE CASCADE,
		INDEX idx_post_uid (post_uid),
		UNIQUE KEY uq_open_post (open_key)
	) ENGINE=InnoDB");

	$pdo->exec("CREATE TABLE `" . SOUDANE . "` (
		id INT AUTO_INCREMENT PRIMARY KEY,
		ip_address VARCHAR(255), yeah TINYINT(1) DEFAULT 0, post_uid INT NULL,
		date_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
		INDEX idx_soudane_vote (post_uid, yeah),
		CONSTRAINT fk_s FOREIGN KEY (post_uid) REFERENCES `" . POSTS . "`(post_uid) ON DELETE CASCADE
	) ENGINE=InnoDB");

	$pdo->exec("CREATE TABLE `" . NOTES . "` (
		id INT AUTO_INCREMENT PRIMARY KEY,
		post_uid INT NOT NULL,
		note_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
		added_by INT NULL, note_text TEXT NOT NULL,
		CONSTRAINT fk_n FOREIGN KEY (post_uid) REFERENCES `" . POSTS . "`(post_uid) ON DELETE CASCADE
	) ENGINE=InnoDB");
}

/**
 * Fixture layout. Every post below is a reply in thread 'T1' unless noted, so a single query over
 * the thread exercises them all at once.
 *
 *   1  OP of T1                      visible
 *   2  plain reply                   visible
 *   3  deleted                       hidden
 *   4  deleted, then restored        visible  (2 history rows)
 *   5  deleted, restored, deleted    hidden   (3 history rows)
 *   6  deleted, then a file deleted  hidden   (the regression: a later attachment-level row
 *                                              must not un-hide an already deleted post)
 *   7  only its file deleted         visible
 *   8  deleted by proxy              hidden
 *   9  OP of T2, deleted             hidden   (T2 itself must disappear)
 *  10  OP of T3, only file deleted   visible  (T3 must survive)
 */
function loadFixtures(PDO $pdo): void {
	$pdo->exec("INSERT INTO `" . ACCOUNTS . "` (id, username) VALUES (1, 'mod')");

	foreach ([['T1', 1, 1], ['T2', 9, 9], ['T3', 10, 10]] as [$uid, $no, $opUid]) {
		$pdo->exec("INSERT INTO `" . THREADS . "` (thread_uid, post_op_number, post_op_post_uid, boardUID)
			VALUES ('{$uid}', {$no}, {$opUid}, 1)");
	}

	$post = $pdo->prepare("INSERT INTO `" . POSTS . "`
		(post_uid, no, boardUID, thread_uid, is_op, pwd, now, name, email, sub, com)
		VALUES (?, ?, 1, ?, ?, '', '', '', '', '', '')");

	foreach ([
		[1, 1, 'T1', 1], [2, 2, 'T1', 0], [3, 3, 'T1', 0], [4, 4, 'T1', 0],
		[5, 5, 'T1', 0], [6, 6, 'T1', 0], [7, 7, 'T1', 0], [8, 8, 'T1', 0],
		[9, 9, 'T2', 1], [10, 10, 'T3', 1],
	] as $row) {
		$post->execute($row);
	}

	// One attachment per post that needs one.
	$file = $pdo->prepare("INSERT INTO `" . FILES . "` (id, post_uid, file_name, stored_filename, file_ext, file_md5)
		VALUES (?, ?, 'a.png', 's.png', 'png', 'd41d8cd98f00b204e9800998ecf8427e')");
	foreach ([[60, 6], [70, 7], [100, 10]] as $row) {
		$file->execute($row);
	}

	$del = $pdo->prepare("INSERT INTO `" . DELETED . "`
		(post_uid, deleted_by, file_only, by_proxy, restored_at, restored_by, file_id)
		VALUES (?, 1, ?, ?, ?, ?, ?)");

	// 3: a single open post-level deletion.
	$del->execute([3, 0, 0, null, null, null]);

	// 4: deleted then restored — the history row stays, but restored_at clears open_key.
	$del->execute([4, 0, 0, '2024-01-01 00:00:00', 1, null]);

	// 5: deleted, restored, deleted again — three rows, only the last one open.
	$del->execute([5, 0, 0, '2024-01-01 00:00:00', 1, null]);
	$del->execute([5, 0, 0, '2024-01-02 00:00:00', 1, null]);
	$del->execute([5, 0, 0, null, null, null]);

	// 6: post deleted first, then one of its files — the attachment row has the higher id, which is
	//    what used to make "the newest row wins" conclude the post was merely missing a file.
	$del->execute([6, 0, 0, null, null, null]);
	$del->execute([6, 1, 0, null, null, 60]);

	// 7: only the attachment was deleted; the post itself stays up.
	$del->execute([7, 1, 0, null, null, 70]);

	// 8: deleted as a side effect of its thread going.
	$del->execute([8, 0, 1, null, null, null]);

	// 9 / 10: an OP deleted outright, and an OP that only lost its file.
	$del->execute([9, 0, 0, null, null, null]);
	$del->execute([10, 1, 0, null, null, 100]);
}

// ---------------------------------------------------------------------------
// Helpers over the production query builders
// ---------------------------------------------------------------------------

/** Post uids the board/thread rendering path considers visible, in a given thread. */
function visiblePostUids(PDO $pdo, string $threadUid, bool $viewDeleted = false): array {
	$sql = getBasePostQuery(POSTS, DELETED, FILES, THREADS, SOUDANE, NOTES, ACCOUNTS, $viewDeleted, false);
	$sql .= ' WHERE p.thread_uid = :thread_uid';

	$stmt = $pdo->prepare($sql);
	$stmt->execute([':thread_uid' => $threadUid]);

	$uids = array_map('intval', array_column($stmt->fetchAll(), 'post_uid'));

	return array_values(array_unique($uids));
}

/** Thread uids that survive the deleted-thread filter. */
function visibleThreadUids(PDO $pdo): array {
	$sql = 'SELECT t.thread_uid FROM `' . THREADS . '` t WHERE 1=1'
		. excludeDeletedThreadsCondition(DELETED);

	return array_column($pdo->query($sql)->fetchAll(), 'thread_uid');
}

// ---------------------------------------------------------------------------
// Cases
// ---------------------------------------------------------------------------

resetSchema($pdo);
loadFixtures($pdo);

echo "\n\033[1mopen_key invariant\033[0m\n";

testCase('the database rejects a second open post-level deletion for one post', function () use ($pdo) {
	// This is the guarantee every read path depends on. If it ever stops holding, "the open row"
	// stops being well-defined and the predicates below become ambiguous.
	try {
		$pdo->exec("INSERT INTO `" . DELETED . "` (post_uid, deleted_by, file_only, by_proxy) VALUES (3, 1, 0, 0)");
	} catch (PDOException $e) {
		assertSameValue('23000', $e->getCode(), 'should fail as an integrity constraint violation');
		return;
	}

	throw new RuntimeException('uq_open_post did not reject a duplicate open deletion');
});

testCase('a post keeps every delete and restore in its history', function () use ($pdo) {
	$rows = (int) $pdo->query("SELECT COUNT(*) FROM `" . DELETED . "` WHERE post_uid = 5")->fetchColumn();
	assertSameValue(3, $rows, 'all three history rows should still be present');

	$open = (int) $pdo->query("SELECT COUNT(*) FROM `" . DELETED . "` WHERE open_key = 5")->fetchColumn();
	assertSameValue(1, $open, 'exactly one of them should be the open one');
});

echo "\n\033[1mpost visibility\033[0m\n";

testCase('an undeleted post is visible', function () use ($pdo) {
	assertVisible(visiblePostUids($pdo, 'T1'), 2, 'plain reply');
});

testCase('a deleted post is hidden', function () use ($pdo) {
	assertHidden(visiblePostUids($pdo, 'T1'), 3, 'post with an open deletion');
});

testCase('a restored post is visible again', function () use ($pdo) {
	assertVisible(visiblePostUids($pdo, 'T1'), 4, 'deleted then restored');
});

testCase('a post deleted, restored, then deleted again is hidden', function () use ($pdo) {
	assertHidden(visiblePostUids($pdo, 'T1'), 5, 'three history rows, newest open');
});

testCase('deleting an attachment of an already deleted post does not un-hide it', function () use ($pdo) {
	// The regression. Under "newest row wins" the attachment-level row shadowed the post-level one
	// and the deleted post reappeared publicly.
	assertHidden(visiblePostUids($pdo, 'T1'), 6, 'post deleted, then its file deleted');
});

testCase('deleting only an attachment leaves the post visible', function () use ($pdo) {
	assertVisible(visiblePostUids($pdo, 'T1'), 7, 'attachment-level deletion only');
});

testCase('a post deleted by proxy is hidden', function () use ($pdo) {
	assertHidden(visiblePostUids($pdo, 'T1'), 8, 'proxy deletion');
});

testCase('the admin view shows every post regardless of deletion', function () use ($pdo) {
	$uids = visiblePostUids($pdo, 'T1', true);

	foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $uid) {
		assertVisible($uids, $uid, 'viewDeleted should hide nothing');
	}
});

echo "\n\033[1mattachment-level state\033[0m\n";

testCase('an attachment-level deletion is reported separately from a post-level one', function () use ($pdo) {
	$sql = 'SELECT p.post_uid, '
		. openDeletionExistsCondition(DELETED, 'p.post_uid') . ' AS post_deleted, '
		. openFileDeletionExistsCondition(DELETED, 'p.post_uid') . ' AS file_deleted '
		. 'FROM `' . POSTS . '` p WHERE p.post_uid IN (3, 6, 7)';

	$state = [];
	foreach ($pdo->query($sql)->fetchAll() as $row) {
		$state[(int) $row['post_uid']] = [(int) $row['post_deleted'], (int) $row['file_deleted']];
	}

	assertSameValue([1, 0], $state[3], 'post 3 is deleted outright');
	assertSameValue([1, 1], $state[6], 'post 6 is deleted and has a deleted attachment');
	assertSameValue([0, 1], $state[7], 'post 7 only lost its attachment');
});

echo "\n\033[1mthread visibility\033[0m\n";

testCase('a thread whose OP is deleted disappears', function () use ($pdo) {
	$threads = visibleThreadUids($pdo);

	if (in_array('T2', $threads, true)) {
		throw new RuntimeException('T2 has a deleted OP and should not be listed');
	}
});

testCase('a thread whose OP merely lost a file survives', function () use ($pdo) {
	$threads = visibleThreadUids($pdo);

	if (!in_array('T3', $threads, true)) {
		throw new RuntimeException('T3 only had an attachment deleted and should still be listed');
	}
});

echo "\n\033[1mreply numbering\033[0m\n";

testCase('objective position numbers only the replies that are rendered', function () use ($pdo) {
	// Pagination slices on this ordinal, so it has to agree with visiblePostUids(): replies 2 and 7
	// are visible, 3/4/5/6/8 vary. Positions must be gapless over the visible set.
	$sql = 'SELECT p.post_uid, ' . objectivePositionSubquery(POSTS, DELETED, 'p', false) . ' AS pos
		FROM `' . POSTS . '` p WHERE p.thread_uid = \'T1\' ORDER BY p.post_uid';

	$positions = [];
	foreach ($pdo->query($sql)->fetchAll() as $row) {
		$positions[(int) $row['post_uid']] = (int) $row['pos'];
	}

	assertSameValue(0, $positions[1], 'the OP is position 0');

	// Visible replies are 2, 4 and 7 — they should be numbered 1, 2, 3 with no gaps.
	assertSameValue(1, $positions[2], 'first visible reply');
	assertSameValue(2, $positions[4], 'second visible reply (restored)');
	assertSameValue(3, $positions[7], 'third visible reply (attachment-only deletion)');
});

echo "\n\033[1mcascade\033[0m\n";

testCase('purging a post removes its deletion history with it', function () use ($pdo) {
	$pdo->exec("DELETE FROM `" . POSTS . "` WHERE post_uid = 5");

	$rows = (int) $pdo->query("SELECT COUNT(*) FROM `" . DELETED . "` WHERE post_uid = 5")->fetchColumn();
	assertSameValue(0, $rows, 'ON DELETE CASCADE should clear the history rows');
});

// ---------------------------------------------------------------------------

foreach ($failed as $failure) {
	echo "\n\033[31mFAILED\033[0m {$failure}\n";
}

echo "\n{$passed} passed, " . count($failed) . " failed\n";

exit($failed === [] ? 0 : 1);
