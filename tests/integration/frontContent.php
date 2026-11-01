<?php

/**
 * Integration tests for front page news and pages against a real MariaDB: paging and drafts,
 * validated writes, slug uniqueness, the author FK, and the action log lines they write.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/frontContent.php
 *
 * Exit code is 0 when everything passes, 1 on failure, 2 when no database is reachable.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__, 2);

require_once $root . '/autoload.php';

// The services translate their errors, so the language libraries are needed too.
Kokonotsuba\kernel\kernel::loadLibraries();

use Kokonotsuba\account\accountRepository;
use Kokonotsuba\action_log\actionLoggerRepository;
use Kokonotsuba\action_log\actionLoggerService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\front_pages\frontPageActionType;
use Kokonotsuba\front_pages\frontPageRepository;
use Kokonotsuba\front_pages\frontPageService;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\news\newsActionType;
use Kokonotsuba\news\newsRepository;
use Kokonotsuba\news\newsService;
use Kokonotsuba\request\request;

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

/** Run $fn and return the BoardException it throws, failing if it throws none. */
function expectRefusal(callable $fn, string $message): BoardException {
	try {
		$fn();
	} catch (BoardException $e) {
		return $e;
	}
	throw new RuntimeException($message);
}

// ---------------------------------------------------------------------------
// Connection and schema
// ---------------------------------------------------------------------------

$dsn = getenv('KOKO_TEST_DSN') ?: '';
$user = getenv('KOKO_TEST_USER') ?: '';
$pass = getenv('KOKO_TEST_PASS') ?: '';

if ($dsn === '' || !preg_match('/host=([^;]+)/', $dsn, $hostMatch) || !preg_match('/dbname=([^;]+)/', $dsn, $nameMatch)) {
	fwrite(STDERR, "Set KOKO_TEST_DSN (with host= and dbname=) / KOKO_TEST_USER / KOKO_TEST_PASS to a scratch database.\n");
	exit(2);
}
preg_match('/charset=([^;]+)/', $dsn, $charsetMatch);
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
$t = static fn(string $key): string => $tableNames[$key];

$dropAll = static function () use ($pdo): void {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
		$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	}
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$dropAll();

(new migrationRunner(
	$db,
	new migrationLedger($db, $t('SCHEMA_MIGRATION_TABLE')),
	new schemaInspector($db, $databaseName),
	$tableNames,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (string $message, string $level): void {}
))->up();

// ---------------------------------------------------------------------------
// Fixtures and services
// ---------------------------------------------------------------------------

$pdo->exec("INSERT INTO `{$t('ACCOUNT_TABLE')}` (username, role, password_hash) VALUES ('kaguya', 100, 'x')");
$authorId = (int)$pdo->lastInsertId();

$accountRepository = new accountRepository($db, $t('ACCOUNT_TABLE'));
$actionLoggerService = new actionLoggerService(
	new actionLoggerRepository($db, $t('ACTIONLOG_TABLE'), $t('BOARD_TABLE')),
	$accountRepository,
	new request([], [], ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_TIME' => time()]),
	new transactionManager($db)
);
$actionLoggerService->registerTypes(newsActionType::class);
$actionLoggerService->registerTypes(frontPageActionType::class);

$news = new newsService(new newsRepository($db, $t('NEWS_TABLE'), $t('ACCOUNT_TABLE')), $actionLoggerService);
$pages = new frontPageService(new frontPageRepository($db, $t('FRONT_PAGES_TABLE')), $actionLoggerService);

$changes = 0;
$news->onChange(function () use (&$changes): void { $changes++; });
$pages->onChange(function () use (&$changes): void { $changes++; });

$lastLog = static fn(): array => $pdo->query("SELECT log_action, action_type, board_uid FROM `{$t('ACTIONLOG_TABLE')}` ORDER BY id DESC LIMIT 1")->fetch();

echo "front page content integration tests ({$databaseName})\n\n";

// ---------------------------------------------------------------------------
// News
// ---------------------------------------------------------------------------

echo "News\n";

$firstId = 0;

testCase('a new entry is stored, logged on the global scope and announced', function () use ($news, $authorId, $lastLog, &$changes, &$firstId) {
	$before = $changes;
	$firstId = $news->createEntry('  BUNKASAI  ', '<p>hi</p>', '', 'kaguya@example.net', true, $authorId);

	$entry = $news->getEntry($firstId, false);
	assertSameValue('BUNKASAI', $entry?->subject, 'subject should be trimmed');
	assertSameValue('kaguya', $entry->displayAuthor(), 'an empty byline should fall back to the account name');
	assertSameValue(null, $entry->updatedAt, 'a new entry has not been edited');

	$log = $lastLog();
	assertSameValue('news.create', $log['action_type'], 'logged as news.create');
	assertSameValue(-1, (int)$log['board_uid'], 'logged on the global scope');
	assertTrueValue(str_contains($log['log_action'], "{{news:{$firstId}|"), 'the log line references the entry: ' . $log['log_action']);
	assertSameValue($before + 1, $changes, 'change listeners run once');
});

testCase('pages hold the newest entries first and count only published ones', function () use ($news, $authorId) {
	for ($i = 2; $i <= 7; $i++) {
		$news->createEntry("entry {$i}", 'body', 'staff', '', true, $authorId);
	}
	$news->createEntry('secret draft', 'body', '', '', false, $authorId);

	$first = $news->listPage(1, 5, false);
	assertSameValue(5, count($first->entries), 'five on the first page');
	assertSameValue('entry 7', $first->entries[0]->subject, 'newest first');
	assertSameValue([7, 2], [$first->total, $first->pageCount], 'seven published over two pages');
	assertSameValue(2, count($news->listPage(2, 5, false)->entries), 'two left on page two');
	assertSameValue([], $news->listPage(9, 5, false)->entries, 'a page past the end is empty');

	$staff = $news->listPage(1, 5, true);
	assertSameValue([8, 'secret draft'], [$staff->total, $staff->entries[0]->subject], 'staff see the draft');
});

testCase('a draft is hidden from visitors but not from staff', function () use ($news, $authorId) {
	$id = $news->createEntry('draft', 'body', '', '', false, $authorId);

	assertSameValue(null, $news->getEntry($id, false), 'visitors get nothing');
	assertSameValue('draft', $news->getEntry($id, true)?->subject, 'staff get the draft');
});

testCase('editing stamps updated_at and logs news.edit', function () use ($news, $lastLog, &$firstId) {
	$news->updateEntry($firstId, 'BUNKASAI 2026', '<p>moved</p>', 'kaguya', '', true);

	$entry = $news->getEntry($firstId, false);
	assertSameValue(['BUNKASAI 2026', '<p>moved</p>', 'kaguya', ''], [$entry->subject, $entry->body, $entry->byline, $entry->email], 'fields replaced');
	assertTrueValue($entry->updatedAt !== null, 'updated_at set');
	assertSameValue('news.edit', $lastLog()['action_type'], 'logged as news.edit');
});

testCase('invalid input is refused before anything is written', function () use ($news, $pdo, $t) {
	$count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t('NEWS_TABLE')}`")->fetchColumn();

	expectRefusal(fn() => $news->createEntry('   ', 'body', '', '', true, null), 'an empty subject should be refused');
	expectRefusal(fn() => $news->createEntry('s', '  ', '', '', true, null), 'an empty body should be refused');
	expectRefusal(fn() => $news->createEntry('s', 'b', '', 'not an email', true, null), 'a bad email should be refused');
	expectRefusal(fn() => $news->createEntry(str_repeat('x', 256), 'b', '', '', true, null), 'a long subject should be refused');

	assertSameValue($count, (int)$pdo->query("SELECT COUNT(*) FROM `{$t('NEWS_TABLE')}`")->fetchColumn(), 'nothing written');
});

testCase('editing or deleting a missing entry is a 404', function () use ($news) {
	assertSameValue(404, expectRefusal(fn() => $news->updateEntry(999999, 's', 'b', '', '', true), 'edit should refuse')->getCode(), 'edit is a 404');
	assertSameValue(404, expectRefusal(fn() => $news->deleteEntry(999999), 'delete should refuse')->getCode(), 'delete is a 404');
});

testCase('deleting removes the entry and logs news.delete', function () use ($news, $authorId, $lastLog) {
	$id = $news->createEntry('doomed', 'body', '', '', true, $authorId);
	$news->deleteEntry($id);

	assertSameValue(null, $news->getEntry($id, true), 'gone');
	assertSameValue('news.delete', $lastLog()['action_type'], 'logged as news.delete');
});

testCase('deleting the author keeps the entry and its byline', function () use ($news, $pdo, $t) {
	$pdo->exec("INSERT INTO `{$t('ACCOUNT_TABLE')}` (username, role, password_hash) VALUES ('leaver', 100, 'x')");
	$leaver = (int)$pdo->lastInsertId();
	$id = $news->createEntry('orphan', 'body', 'old staff', '', true, $leaver);

	$pdo->exec("DELETE FROM `{$t('ACCOUNT_TABLE')}` WHERE id = {$leaver}");

	$entry = $news->getEntry($id, false);
	assertSameValue([null, 'old staff'], [$entry?->authorId, $entry?->displayAuthor()], 'author cleared, byline kept');
});

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------

echo "\nPages\n";

$faqId = 0;

testCase('a page is found by its slug whatever the case', function () use ($pages, $authorId, $lastLog, &$faqId) {
	$faqId = $pages->createPage(' FAQ ', 'FAQ', '<h2>q</h2>', true, true, 20, $authorId);

	assertSameValue('faq', $pages->getPage('Faq', false)?->slug, 'stored and found lowercased');
	assertSameValue('page.create', $lastLog()['action_type'], 'logged as page.create');
	assertTrueValue(str_contains($lastLog()['log_action'], '{{page:faq|'), 'the log line references the slug');
});

testCase('the navigation lists published, listed pages in order', function () use ($pages, $authorId) {
	$pages->createPage('rules', 'Rules', 'r', true, true, 10, $authorId);
	$pages->createPage('japanese2', 'Hidden', 'j', true, false, 5, $authorId);
	$pages->createPage('wip', 'Draft', 'w', false, true, 1, $authorId);

	assertSameValue(['rules', 'faq'], array_map(fn($p) => $p->slug, $pages->getNavPages()), 'unlisted and draft pages left out, ordered by nav_order');
	assertSameValue('Hidden', $pages->getPage('japanese2', false)?->title, 'an unlisted page is still reachable');
	assertSameValue(null, $pages->getPage('wip', false), 'a draft is hidden from visitors');
	assertSameValue(4, count($pages->getAllPages()), 'staff list every page');
});

testCase('slugs must be valid, unreserved and unique', function () use ($pages, $authorId, &$faqId) {
	expectRefusal(fn() => $pages->createPage('news', 'News', 'b', true, true, 0, $authorId), 'news is reserved');
	expectRefusal(fn() => $pages->createPage('bad slug', 'T', 'b', true, true, 0, $authorId), 'spaces are refused');
	expectRefusal(fn() => $pages->createPage('FAQ', 'Again', 'b', true, true, 0, $authorId), 'a taken slug is refused, whatever the case');
	expectRefusal(fn() => $pages->createPage('ok', '  ', 'b', true, true, 0, $authorId), 'an empty title is refused');

	$pages->updatePage($faqId, 'faq', 'FAQ v2', 'b', true, true, 20);
	assertSameValue('FAQ v2', $pages->getPage('faq', false)?->title, 'a page may keep its own slug');
	expectRefusal(fn() => $pages->updatePage($faqId, 'rules', 'FAQ', 'b', true, true, 20), 'renaming onto another page is refused');
});

testCase('deleting a page frees its slug', function () use ($pages, $authorId, $lastLog) {
	$id = $pages->createPage('temp', 'Temp', 'b', true, true, 0, $authorId);
	$pages->deletePage($id);

	assertSameValue('page.delete', $lastLog()['action_type'], 'logged as page.delete');
	$pages->createPage('temp', 'Temp again', 'b', true, true, 0, $authorId);
	assertSameValue('Temp again', $pages->getPage('temp', false)?->title, 'the slug is free again');
});

testCase('imports keep their date, skip repeats and stay silent', function () use ($news, $pages, $lastLog, &$changes) {
	$before = $changes;
	$logBefore = $lastLog()['id'] ?? null;

	$id = $news->importEntry('OLD NEWS', '<p>b</p>', 'kuz', '', '2020-06-05 15:56:00');
	assertSameValue('2020-06-05 15:56:00', $news->getEntry((int)$id, false)?->createdAt, 'original date kept');
	assertSameValue(null, $news->importEntry('OLD NEWS', '<p>b</p>', 'kuz', '', '2020-06-05 15:56:00'), 'same subject and date skipped');

	assertTrueValue($pages->importPage('imported', 'Imported', '<p>b</p>', false, 3) !== null, 'page imported');
	assertSameValue(null, $pages->importPage('imported', 'Again', '<p>b</p>', false, 3), 'taken slug skipped');

	assertSameValue($before, $changes, 'no change events');
	assertSameValue($logBefore, $lastLog()['id'] ?? null, 'no action log lines');
});

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
