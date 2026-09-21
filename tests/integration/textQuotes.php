<?php

/**
 * Integration tests for text quote lookups, against a real MariaDB.
 *
 * The unit tests pin the rules against an in-memory thread; what only an engine can answer is
 * whether the queries behind them mean the same thing: the column collation is accent- and
 * case-insensitive, so LIKE is looser than the matcher and every row it returns has to be
 * checked again in PHP. Random threads are resolved twice - once through the real repository
 * and once through the in-memory one - and the two must agree.
 *
 * The page script is checked against the same threads too: its rules live in
 * static/js/quoteLookup.js and node runs them on the rows this script seeded, so a change to
 * one side that the other did not get fails here.
 *
 * Usage - point this at a throwaway database; it drops every table in it on each run:
 *
 *   KOKO_TEST_DSN='mysql:host=127.0.0.1;dbname=koko_test;charset=utf8mb4' \
 *   KOKO_TEST_USER=claude KOKO_TEST_PASS=claude_local_dev \
 *   php tests/integration/textQuotes.php
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
require_once $root . '/tests/framework/InMemoryTextQuoteRepository.php';

use Koko\Tests\Framework\InMemoryTextQuoteRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\migrations\migrationLedger;
use Kokonotsuba\migrations\migrationRunner;
use Kokonotsuba\migrations\schemaInspector;
use Kokonotsuba\quote_link\textQuoteMatcher;
use Kokonotsuba\quote_link\textQuoteRepository;
use Kokonotsuba\quote_link\textQuoteResolver;

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

function assertSameValue(mixed $expected, mixed $actual, string $message = 'not the expected post'): void {
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . "\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true)
		);
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

foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
	$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
	$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

(new migrationRunner(
	$databaseConnection,
	new migrationLedger($databaseConnection, $tableNames['SCHEMA_MIGRATION_TABLE']),
	new schemaInspector($databaseConnection, $nameMatch[1]),
	$tableNames,
	$root,
	Kokonotsuba\KOKO_VERSION,
	static function (string $message, string $level): void {}
))->up();

$pdo->exec(
	"INSERT INTO `{$tableNames['BOARD_TABLE']}` (board_uid, board_identifier, board_title, storage_directory_name, listed)
	 VALUES (1, 'a', 'Board A', 'a', 1)"
);

// ---------------------------------------------------------------------------
// Seeding
// ---------------------------------------------------------------------------

$insertThread = $pdo->prepare(
	"INSERT INTO `{$tableNames['THREAD_TABLE']}` (thread_uid, post_op_number, post_op_post_uid, boardUID)
	 VALUES (:thread, :no, :uid, 1)"
);

$insertPost = $pdo->prepare(
	"INSERT INTO `{$tableNames['POST_TABLE']}`
	 (post_uid, no, boardUID, thread_uid, post_position, is_op, root, pwd, now, name, email, sub, com, host, status, text_format)
	 VALUES (:uid, :no, 1, :thread, :pos, :is_op, NOW(), '', '', 'Anonymous', '', '', :com, '10.0.0.1', '', :format)"
);

$insertFile = $pdo->prepare(
	"INSERT INTO `{$tableNames['FILE_TABLE']}` (post_uid, file_name, stored_filename, file_ext, file_md5, file_size, is_hidden)
	 VALUES (:uid, :name, :stored, :ext, :md5, 1, :hidden)"
);

$markDeleted = $pdo->prepare(
	"INSERT INTO `{$tableNames['DELETED_POSTS_TABLE']}` (post_uid, deleted_by, file_only, by_proxy) VALUES (:uid, NULL, 0, 0)"
);

/**
 * Seed one thread.
 *
 * @param array $posts Each ['uid', 'no', 'is_op', 'com', 'text_format', 'files' => [[name, ext, hidden]], 'deleted']
 */
$seedThread = static function (string $threadUid, array $posts) use ($insertThread, $insertPost, $insertFile, $markDeleted): void {
	$op = null;
	foreach ($posts as $post) {
		if ($post['is_op']) {
			$op = $post;
		}
	}

	$insertThread->execute([':thread' => $threadUid, ':no' => $op['no'], ':uid' => $op['post_uid']]);

	// the OP row goes in first whatever its uid, since the thread points at it
	usort($posts, static fn(array $a, array $b): int => [!$a['is_op'], $a['post_uid']] <=> [!$b['is_op'], $b['post_uid']]);

	foreach ($posts as $position => $post) {
		$insertPost->execute([
			':uid' => $post['post_uid'], ':no' => $post['no'], ':thread' => $threadUid,
			':pos' => $post['is_op'] ? 0 : $position, ':is_op' => (int)$post['is_op'],
			':com' => $post['com'], ':format' => $post['text_format'],
		]);

		foreach ($post['files'] ?? [] as $index => [$name, $ext, $hidden]) {
			$insertFile->execute([
				':uid' => $post['post_uid'], ':name' => $name, ':stored' => $post['post_uid'] . '-' . $index,
				':ext' => $ext, ':md5' => str_pad((string)$post['post_uid'], 32, '0'), ':hidden' => (int)$hidden,
			]);
		}

		if (!empty($post['deleted'])) {
			$markDeleted->execute([':uid' => $post['post_uid']]);
		}
	}
};

$post = static fn(int $uid, int $no, string $com, bool $isOp = false, array $files = [], bool $deleted = false, int $format = 1): array =>
	['post_uid' => $uid, 'no' => $no, 'is_op' => $isOp, 'com' => $com, 'text_format' => $format, 'files' => $files, 'deleted' => $deleted];

// A hand-written thread covering the cases the rules are about.
$handThread = [
	$post(1, 101, "the opening post\nmentions cats", true, [['op image', 'png', false]]),
	$post(2, 102, 'cats are fine'),
	$post(3, 103, ">cats are fine\nno they are not"),
	$post(4, 104, 'dogs though', false, [['dog', 'jpg', false]]),
	$post(5, 105, 'cats are fine', false, [], true),
	$post(6, 106, 'CATS ARE FINE and accented: café'),
	$post(7, 107, 'Tom &amp; Jerry &lt;3<br />second line', false, [], false, 0),
	$post(8, 108, 'hidden file here', false, [['secret', 'png', true]]),
	$post(9, 109, '100% sure_thing \\o/'),
	$post(10, 110, 'zzz the post the quotes are typed in zzz'),
];
$seedThread('t-hand', $handThread);

$textQuoteRepository = new textQuoteRepository(
	$databaseConnection,
	$tableNames['POST_TABLE'],
	$tableNames['THREAD_TABLE'],
	$tableNames['DELETED_POSTS_TABLE'],
	$tableNames['FILE_TABLE']
);
$resolver = new textQuoteResolver($textQuoteRepository);

$resolve = static fn(string $thread, int $before, string $needle, bool $quoted = false): ?int
	=> $resolver->resolve($thread, $before, $needle, $quoted);

echo "\nText quote lookups\n";

testCase('the nearest earlier post is the source', function () use ($resolve) {
	assertSameValue(2, $resolve('t-hand', 10, 'cats are fine'), 'nearest reply');
	assertSameValue(1, $resolve('t-hand', 2, 'mentions cats'), 'the OP');
});

testCase('a later post is never the source', function () use ($resolve) {
	assertSameValue(null, $resolve('t-hand', 4, 'dogs though'), 'its own text');
	assertSameValue(null, $resolve('t-hand', 2, 'dogs though'), 'a post after it');
});

testCase('a deleted post is invisible', function () use ($resolve) {
	assertSameValue(2, $resolve('t-hand', 6, 'cats are fine'), 'skips the deleted post 5');
});

testCase('a post that only repeats the quote is not the source', function () use ($resolve) {
	assertSameValue(2, $resolve('t-hand', 10, 'cats are fine'));
	assertSameValue(3, $resolve('t-hand', 10, 'cats are fine', true), 'a quote of a quote');
});

testCase('matching stays exact although the collation is not', function () use ($resolve) {
	// the column's own collation is accent- and case-insensitive, so the search casts to binary
	assertSameValue(2, $resolve('t-hand', 10, 'cats are fine'), 'case');
	assertSameValue(6, $resolve('t-hand', 10, 'CATS ARE FINE'), 'the other case');
	assertSameValue(6, $resolve('t-hand', 10, 'café'), 'the accented form');
	assertSameValue(null, $resolve('t-hand', 10, 'cafe'), 'the unaccented form is a different string');
});

testCase('wildcards and backslashes in a needle are literal', function () use ($resolve) {
	assertSameValue(9, $resolve('t-hand', 10, '100% sure_thing \\o/'));
	assertSameValue(null, $resolve('t-hand', 10, '100%%'));
	assertSameValue(null, $resolve('t-hand', 10, 'sure_'.'thing2'));
	assertSameValue(null, $resolve('t-hand', 10, '1_0%'));
});

testCase('file names resolve, and only exactly', function () use ($resolve) {
	assertSameValue(4, $resolve('t-hand', 10, 'dog.jpg'));
	assertSameValue(1, $resolve('t-hand', 10, 'op image.png'), 'the OP is the last resort');
	assertSameValue(null, $resolve('t-hand', 10, 'DOG.jpg'), 'names are compared exactly');
	assertSameValue(null, $resolve('t-hand', 10, 'dog.png'), 'the extension is part of it');
	assertSameValue(null, $resolve('t-hand', 10, 'secret.png'), 'a hidden file is not shown');
});

testCase('post numbers resolve through the thread', function () use ($resolve) {
	assertSameValue(2, $resolve('t-hand', 10, '102'));
	assertSameValue(1, $resolve('t-hand', 10, 'No.101'));
	assertSameValue(null, $resolve('t-hand', 3, 'No.104'), 'a later post');
	assertSameValue(null, $resolve('t-hand', 10, '105'), 'a deleted post');
	assertSameValue(null, $resolve('t-hand', 10, '999'), 'no such post');
	assertSameValue(null, $resolve('t-hand', 10, '0'));
});

testCase('a legacy row is found through the text it renders to', function () use ($resolve) {
	assertSameValue(7, $resolve('t-hand', 10, 'Tom & Jerry <3'));
	assertSameValue(7, $resolve('t-hand', 10, 'second line'));
	assertSameValue(null, $resolve('t-hand', 10, '&amp;'), 'the stored escaping is not searchable');
	assertSameValue(null, $resolve('t-hand', 10, 'Jerry <3<br />second'), 'nor its markup');
});

testCase('a lookup naming a thread the post is not in finds nothing', function () use ($resolver) {
	assertSameValue(null, $resolver->resolve('t-other', 10, 'cats are fine', false), 'post 10 is in t-hand');
	assertSameValue(null, $resolver->resolve('t-hand', 9999999, 'cats are fine', false), 'no such post');
});

testCase('another thread is never searched', function () use ($resolve, $seedThread, $post) {
	$seedThread('t-other', [$post(20, 201, 'cats are fine', true), $post(21, 202, 'unique to the other thread'), $post(22, 203, 'zzz quoting post zzz')]);

	assertSameValue(null, $resolve('t-hand', 10, 'unique to the other thread'));
	assertSameValue(20, $resolve('t-other', 22, 'cats are fine'), 'its own OP');
});

testCase('nothing comes before an opening post', function () use ($resolve, $seedThread, $post) {
	// a merged thread's OP can hold a higher uid than its replies
	$seedThread('t-merged', [$post(300, 301, 'merged op', true), $post(30, 302, 'older reply text'), $post(310, 303, 'zzz')]);

	assertSameValue(null, $resolve('t-merged', 300, 'older reply'), 'from the opening post');
	assertSameValue(300, $resolve('t-merged', 30, 'merged op'));
});

testCase('a thread of nothing but repeats is given up on, cheaply', function () use ($textQuoteRepository, $seedThread, $post, $databaseConnection) {
	$posts = [$post(1000, 1000, 'an opening post about nothing', true)];
	for ($uid = 1001; $uid < 1200; $uid++) {
		$posts[] = $post($uid, $uid, '>the original line');
	}
	$seedThread('t-repeats', $posts);

	// counted on the resolver's own connection; SHOW STATUS is not itself a SELECT
	$selects = static fn(): int => (int)$databaseConnection->fetchValue("SHOW SESSION STATUS LIKE 'Com_select'", [], 1);

	$before = $selects();
	$found = (new textQuoteResolver($textQuoteRepository))->resolve('t-repeats', 1199, 'the original line', false);
	$ran = $selects() - $before;

	assertSameValue(null, $found, 'every candidate only repeats the quote');

	if ($ran > textQuoteResolver::MAX_STATEMENTS) {
		throw new RuntimeException("a hopeless lookup ran {$ran} statements");
	}

	echo "      the worst case ran {$ran} statements\n";
});

testCase('an ordinary lookup is one statement', function () use ($resolver, $databaseConnection) {
	$selects = static fn(): int => (int)$databaseConnection->fetchValue("SHOW SESSION STATUS LIKE 'Com_select'", [], 1);

	foreach ([['cats are fine', 2], ['dog.jpg', 4], ['No.101', 1], ['nothing anybody ever wrote', null]] as [$needle, $expected]) {
		$before = $selects();
		$found = $resolver->resolve('t-hand', 10, $needle, false);
		$ran = $selects() - $before;

		assertSameValue($expected, $found, 'lookup of ' . var_export($needle, true));

		if ($ran !== 1) {
			throw new RuntimeException('lookup of ' . var_export($needle, true) . " ran {$ran} statements");
		}
	}
});

testCase('the reply search uses the thread index and reads a bounded number of rows', function () use ($pdo, $tableNames) {
	$sql = "SELECT p.post_uid FROM (
				SELECT w.post_uid FROM `{$tableNames['POST_TABLE']}` w
				WHERE w.thread_uid = 't-repeats' AND w.is_op = 0 AND w.post_uid < 5000
				ORDER BY w.post_uid DESC LIMIT 1000
			) recent
			INNER JOIN `{$tableNames['POST_TABLE']}` p ON p.post_uid = recent.post_uid
			WHERE CAST(p.com AS BINARY) LIKE CAST('%x%' AS BINARY)
			ORDER BY p.post_uid DESC LIMIT 25";

	$plan = $pdo->query('EXPLAIN ' . $sql)->fetchAll();
	$keys = array_column($plan, 'key');
	$rows = array_sum(array_map('intval', array_column($plan, 'rows')));

	if (!in_array('idx_posts_thread_rank', $keys, true) && !in_array('thread_uid', $keys, true)) {
		throw new RuntimeException('the window query is not using a thread index: ' . implode(', ', array_filter($keys)));
	}

	if ($rows > 5000) {
		throw new RuntimeException("the plan reads {$rows} rows");
	}
});

// ---------------------------------------------------------------------------
// Differential: the real queries against the in-memory rules
// ---------------------------------------------------------------------------

echo "\nAgainst the in-memory rules\n";

/** Random threads, seeded into MariaDB and resolved both ways. */
$randomThreads = [];
mt_srand((int)(getenv('KOKO_TEST_SEED') ?: 20260921));

$vocabulary = ['cat', 'Cat', 'CAT', 'dog', 'the', 'café', 'cafe', '草', 'a&b', '<3', '100%', 'snake_case', "it's", 'file.png', 'No.3', '12'];
$pickWord = static fn(): string => $vocabulary[mt_rand(0, count($vocabulary) - 1)];

for ($thread = 0; $thread < 12; $thread++) {
	$threadUid = 'r-' . $thread;
	$posts = [];
	$uid = 10000 + $thread * 100;
	$size = mt_rand(1, 10);

	for ($i = 0; $i < $size; $i++) {
		$lines = [];
		for ($line = mt_rand(0, 3); $line > 0; $line--) {
			$words = [];
			for ($w = mt_rand(0, 3); $w > 0; $w--) {
				$words[] = $pickWord();
			}
			$lines[] = (mt_rand(0, 2) === 0 ? '>' : '') . implode(' ', $words);
		}

		$legacy = mt_rand(0, 3) === 0;
		$text = implode("\n", $lines);
		$files = mt_rand(0, 2) === 0 ? [[$pickWord(), mt_rand(0, 1) ? 'png' : 'jpg', false]] : [];

		$uid += mt_rand(1, 4);
		$posts[] = $post(
			$uid,
			10000 + $thread * 100 + $i,
			$legacy ? nl2br(htmlspecialchars($text, ENT_QUOTES), false) : $text,
			$i === 0,
			$files,
			$i > 0 && mt_rand(0, 5) === 0,
			$legacy ? 0 : 1
		);
	}

	$seedThread($threadUid, $posts);
	$randomThreads[$threadUid] = $posts;
}

$needles = [];
foreach ($randomThreads as $posts) {
	foreach ($posts as $entry) {
		foreach (textQuoteMatcher::lines($entry['com'], Kokonotsuba\post\textFormat::fromStored($entry['text_format'])) as $line) {
			$line = ltrim($line, '>');
			if (trim($line) !== '') {
				$needles[] = trim($line);
				$words = preg_split('/\s+/', trim($line));
				$needles[] = $words[0];
			}
		}
		foreach ($entry['files'] as [$name, $ext]) {
			$needles[] = textQuoteMatcher::displayedFileName($name, $ext);
		}
		$needles[] = (string)$entry['no'];
	}
}
$needles = array_values(array_unique(array_merge($needles, ['cat', 'CAT', 'café', 'cafe', 'nothing here', '100%', 'snake_case'])));

testCase('every lookup agrees with the in-memory rules', function () use ($randomThreads, $resolver, $needles) {
	$checked = 0;

	foreach ($randomThreads as $threadUid => $posts) {
		$memory = new textQuoteResolver(new InMemoryTextQuoteRepository($posts, $threadUid));
		$uids = array_column($posts, 'post_uid');

		foreach ($uids as $beforeUid) {
			foreach ($needles as $needle) {
				foreach ([false, true] as $quoted) {
					$fromDatabase = $resolver->resolve($threadUid, $beforeUid, $needle, $quoted);
					$fromMemory = $memory->resolve($threadUid, $beforeUid, $needle, $quoted);
					$checked++;

					if ($fromDatabase !== $fromMemory) {
						throw new RuntimeException(
							"thread {$threadUid}, before {$beforeUid}, needle " . var_export($needle, true)
							. ($quoted ? ' (quoted)' : '')
							. "\n  database: " . var_export($fromDatabase, true)
							. "\n  memory:   " . var_export($fromMemory, true)
						);
					}
				}
			}
		}
	}

	if ($checked < 1000) {
		throw new RuntimeException("only {$checked} lookups were compared");
	}

	echo "      {$checked} lookups compared\n";
});

// ---------------------------------------------------------------------------
// Differential: the page script against the server
// ---------------------------------------------------------------------------

echo "\nAgainst the page script\n";

testCase('the page script resolves the same posts as the server', function () use ($randomThreads, $resolver, $needles, $root) {
	if (trim((string)shell_exec('command -v node')) === '') {
		echo "      node is not installed; skipped\n";
		return;
	}

	// what qu3.js would read off a page holding the whole thread, in thread order
	$cases = [];
	foreach ($randomThreads as $threadUid => $posts) {
		$ordered = $posts;
		usort($ordered, static fn(array $a, array $b): int => [!$a['is_op'], $a['post_uid']] <=> [!$b['is_op'], $b['post_uid']]);
		$ordered = array_values(array_filter($ordered, static fn(array $p): bool => empty($p['deleted'])));

		$records = [];
		foreach ($ordered as $entry) {
			$format = Kokonotsuba\post\textFormat::fromStored($entry['text_format']);
			$records[] = [
				'id' => 'p1_' . $entry['no'],
				'number' => $entry['no'],
				'lines' => textQuoteMatcher::lines($entry['com'], $format),
				'fileNames' => array_map(
					static fn(array $file): string => textQuoteMatcher::displayedFileName($file[0], $file[1]),
					$entry['files']
				),
				'gapBefore' => false,
			];
		}

		foreach ($ordered as $index => $entry) {
			foreach ($needles as $needle) {
				foreach ([false, true] as $quoted) {
					$expected = $resolver->resolve($threadUid, $entry['post_uid'], $needle, $quoted);
					$expectedId = null;
					foreach ($ordered as $candidate) {
						if ($candidate['post_uid'] === $expected) {
							$expectedId = 'p1_' . $candidate['no'];
						}
					}

					$cases[] = [
						'posts' => $records,
						'selfIndex' => $index,
						'text' => $needle,
						'quoted' => $quoted,
						'expected' => $expectedId,
						'where' => "thread {$threadUid}, post {$entry['post_uid']}, needle " . var_export($needle, true) . ($quoted ? ' (quoted)' : ''),
					];
				}
			}
		}
	}

	$inputFile = tempnam(sys_get_temp_dir(), 'kokoquote');
	file_put_contents($inputFile, json_encode($cases));

	$checker = <<<'JS'
	const lookup = require(process.argv[2] + '/static/js/quoteLookup.js')
	const cases = JSON.parse(require('fs').readFileSync(process.argv[3], 'utf8'))
	const mismatches = []
	for (const c of cases) {
		const got = lookup.findSource(c.posts, c.selfIndex, c.text, c.quoted)
		if (got.id !== c.expected) mismatches.push(`${c.where}\n  server: ${c.expected}\n  page:   ${got.id}`)
	}
	console.log(JSON.stringify({ checked: cases.length, mismatches: mismatches.slice(0, 5), total: mismatches.length }))
	JS;

	$scriptFile = tempnam(sys_get_temp_dir(), 'kokoquotejs');
	file_put_contents($scriptFile, $checker);

	$output = shell_exec('node ' . escapeshellarg($scriptFile) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($inputFile) . ' 2>&1');
	unlink($inputFile);
	unlink($scriptFile);

	$result = json_decode((string)$output, true);
	if (!is_array($result)) {
		throw new RuntimeException('the page script could not be run: ' . trim((string)$output));
	}

	if ($result['total'] > 0) {
		throw new RuntimeException(
			"{$result['total']} of {$result['checked']} lookups disagree:\n" . implode("\n", $result['mismatches'])
		);
	}

	echo "      {$result['checked']} lookups compared\n";
});

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

echo "\n";

if ($failed) {
	echo "\033[31m" . count($failed) . " failed\033[0m, {$passed} passed\n\n";
	foreach ($failed as $failure) {
		echo "  - {$failure}\n";
	}
	exit(1);
}

echo "\033[32mAll {$passed} passed.\033[0m\n";
exit(0);
