<?php

/**
 * Fuzz targets for page rebuilding.
 *
 * Included by tests/fuzz.php with $fuzzer in scope.
 *
 * A rebuild draws threads through the template engine, stores the anonymous markup in the
 * thread fragment cache, wraps the page in a pager and thread navigation, and finalises the
 * HTML before it is written to disk. None of that needs a database: the cache is a directory,
 * the template engine is a compiler, and the rest are functions of their arguments. What the
 * database would supply (thread rows, post text, config) is what gets fuzzed.
 */

use Koko\Tests\Framework\Fuzzer;
use Kokonotsuba\board\boardRebuilder;
use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\post\Post;
use Kokonotsuba\template\templateEngine;
use Kokonotsuba\thread\Thread;

use function Kokonotsuba\libraries\html\buildThreadNavButtons;
use function Kokonotsuba\libraries\html\drawBoardPager;
use function Kokonotsuba\libraries\html\getPageForPostPosition;
use function Kokonotsuba\libraries\html\validateAndClampPagination;
use function Puchiko\strings\html_minify;

require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/html/miscPartials.php';
require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/html/pagers.php';
require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/html/postHtmlFunctions.php';

// A scratch directory for the fragment cache, thrown away when the run ends.
$rebuildScratch = sys_get_temp_dir() . '/koko-fuzz-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '/';
register_shutdown_function(static function () use ($rebuildScratch): void {
	if (!is_dir($rebuildScratch)) {
		return;
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rebuildScratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $entry) {
		$entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
	}
	@rmdir($rebuildScratch);
});

$rebuildValidUtf8 = static fn(string $s): bool => mb_check_encoding($s, 'UTF-8');

/** A value as a database row or a poster might hand the rebuild: hostile text, sometimes not UTF-8. */
$rebuildText = static function (int $maxLen = 40): string {
	$text = Fuzzer::nastyString($maxLen);
	if (Fuzzer::int(0, 7) === 0) {
		$text .= Fuzzer::pick(["\xff", "\xc3", "\xed\xa0\x80"]);
	}
	return $text;
};

/** A key for the fragment cache: usually the shape a real uid, variant or stamp has, sometimes not. */
$rebuildCacheKey = static fn(): string => Fuzzer::pick([
	bin2hex(random_bytes(8)),
	'index-' . Fuzzer::int(0, 50),
	'thread-' . Fuzzer::pick(['all', Fuzzer::int(1, 9)]),
	Fuzzer::int(0, 999) . '-' . Fuzzer::int(0, PHP_INT_MAX >> 1),
	'',
	'.',
	'..',
	'../../etc/passwd',
	'*',
	'a.*.html',
	"index-3\n",
	str_repeat('x', 65),
	str_repeat('y', 64),
	Fuzzer::nastyString(20),
]);

// ---- threadFragmentCache ----------------------------------------------------------

// put/get/forget round-trip: what was stored comes back byte for byte, a newer stamp of the same
// variant retires the older one, other threads are untouched, forget drops every variant, and
// hostile keys never escape the cache directory or leave temp files behind.
$fuzzer->target(
	'rebuild:threadFragmentCache roundTrip',
	function (string $uid, string $variant, string $stamp, string $stamp2, string $html, string $otherUid, string $otherHtml) use ($rebuildScratch) {
		$dir = $rebuildScratch . 'cache-' . Fuzzer::int(0, 3) . '/';
		$cache = new threadFragmentCache($dir);
		$cache->clear();

		$stored = $cache->put($uid, $variant, $stamp, $html);
		$got = $cache->get($uid, $variant, $stamp);
		$storedOther = $cache->put($otherUid, $variant, $stamp, $otherHtml);
		$stillGot = $cache->get($uid, $variant, $stamp);

		$restamped = $cache->put($uid, $variant, $stamp2, $html . 'v2');
		$afterRestamp = ['old' => $cache->get($uid, $variant, $stamp), 'new' => $cache->get($uid, $variant, $stamp2)];

		$cache->forget($uid);
		$afterForget = ['mine' => $cache->get($uid, $variant, $stamp2), 'other' => $cache->get($otherUid, $variant, $stamp)];

		$entries = array_values(array_diff(scandir($dir) ?: [], ['.', '..', 'epochs']));
		$cache->clear();
		$afterClear = array_values(array_diff(scandir($dir) ?: [], ['.', '..', 'epochs']));

		return compact('stored', 'got', 'storedOther', 'stillGot', 'restamped', 'afterRestamp', 'afterForget', 'entries', 'afterClear') + ['sameUid' => $uid === $otherUid, 'sameStamp' => $stamp === $stamp2];
	},
	fn() => [$rebuildCacheKey(), $rebuildCacheKey(), $rebuildCacheKey(), $rebuildCacheKey(), $rebuildText(200), $rebuildCacheKey(), $rebuildText(50)],
	[
		['put succeeds', fn($r) => $r['stored'] === true && $r['storedOther'] === true && $r['restamped'] === true],
		['get returns what was put', fn($r, $a) => $r['got'] === $a[4]],
		['another thread does not clobber it', fn($r, $a) => $r['sameUid'] || $r['stillGot'] === $a[4]],
		['a new stamp retires the old one', fn($r) => $r['sameStamp'] || $r['afterRestamp']['old'] === null],
		['the new stamp is served', fn($r, $a) => $r['afterRestamp']['new'] === $a[4] . 'v2'],
		['forget drops every variant of the thread', fn($r) => $r['afterForget']['mine'] === null],
		['forget leaves other threads alone', fn($r, $a) => $r['sameUid'] || $r['afterForget']['other'] === $a[6]],
		['no temp files left behind', fn($r) => !array_filter($r['entries'], fn(string $e): bool => str_ends_with($e, '.tmp'))],
		['every file has the cache file shape', fn($r) => !array_filter($r['entries'], fn(string $e): bool => !preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.v\d+\.html$/', $e))],
		['clear empties the directory', fn($r) => $r['afterClear'] === []],
	]
);

// The stamp is a function of the row: digits only, and it changes when the post count does.
$fuzzer->target(
	'rebuild:threadFragmentCache::stampFor',
	fn(array $row) => threadFragmentCache::stampFor(new Thread($row)),
	fn() => [[
		'number_of_posts' => Fuzzer::pick([Fuzzer::int(0, 5000), '12', '', null, 'abc', -1]),
		'last_reply_time' => Fuzzer::pick(['2024-01-02 03:04:05', '', null, Fuzzer::nastyString(12), '0000-00-00 00:00:00', (string)Fuzzer::int(0, PHP_INT_MAX)]),
	]],
	[
		['count dash digits', fn($r) => preg_match('/^-?\d+-\d*$/', $r) === 1],
		['file-name safe', fn($r) => preg_match('/^[A-Za-z0-9_-]+$/', $r) === 1],
		['a reply changes the stamp', function ($r, $a) {
			$row = $a[0];
			$row['number_of_posts'] = (int)$row['number_of_posts'] + 1;
			return threadFragmentCache::stampFor(new Thread($row)) !== $r;
		}],
	]
);

// ---- templateEngine ----------------------------------------------------------------

$rebuildEngineConfig = ['INPUT_MAX' => 10, 'PIXMICAT_LANGUAGE' => 'en_US', 'LIVE_INDEX_FILE' => 'koko.php', 'ADMINBAR_OVERBOARD_BUTTON' => true];
$rebuildEngine = new templateEngine(KOKO_TEST_ROOT . '/templates/kokoimg', ['config' => $rebuildEngineConfig, 'boardData' => ['title' => 'Fuzz <b>board</b>']]);

$rebuildCompile = new ReflectionMethod(templateEngine::class, 'compileText');
$rebuildCompile->setAccessible(true);
$rebuildRender = new ReflectionMethod(templateEngine::class, 'renderNodes');
$rebuildRender->setAccessible(true);

$rebuildPlaceholderNames = ['A', 'B', 'C', 'D', 'E'];

/** A value for a placeholder: often text that looks like template syntax, which must stay inert. */
$rebuildTemplateValue = static fn(): mixed => Fuzzer::pick([
	Fuzzer::nastyString(12),
	'{$A}', '{$B}{$C}', '{$}', '{$unknown}',
	"<!--&IF(\$A,'x','y')-->", '<!--&FOREACH($A,\'REPLY\')-->', '<!--&REPLY/-->', "<!--&FILE('/etc/hostname')-->",
	"\x02", "\x030\x02", "\x020\x03", "\x02" . Fuzzer::int(0, 9) . "\x03",
	'0', '', 0, 1, -3, 1.5, null, true, false, [], ['x' => 1],
]);

/** A template over the placeholder/IF grammar, with its expected rendering computed by a reference walk. */
$rebuildTemplateCase = static function () use ($rebuildPlaceholderNames, $rebuildTemplateValue): array {
	$vals = [];
	foreach ($rebuildPlaceholderNames as $name) {
		if (Fuzzer::int(0, 3) > 0) {
			$vals['{$' . $name . '}'] = $rebuildTemplateValue();
		}
	}

	// No bare "{$" outside a placeholder: the engine reads "{$" up to the next "}" as one token even
	// across a directive, which is fine for real templates and meaningless to a reference walk.
	$literal = static fn(): string => Fuzzer::pick(['', 'text', '0', '12', ' ', "\n", '{', '}', '{ $A}', 'A}', '<b>', '&amp;', '日本', "\x01", '<!--', '-->', '-->x<!--']);
	$branch = static function () use ($literal, $rebuildPlaceholderNames): string {
		$out = '';
		for ($i = Fuzzer::int(0, 3); $i > 0; $i--) {
			$out .= Fuzzer::bool() ? $literal() : '{$' . Fuzzer::pick($rebuildPlaceholderNames) . '}';
		}
		return $out;
	};

	$template = '';
	for ($i = Fuzzer::int(0, 8); $i > 0; $i--) {
		switch (Fuzzer::int(0, 2)) {
			case 0:
				$template .= $literal();
				break;
			case 1:
				$template .= '{$' . Fuzzer::pick($rebuildPlaceholderNames) . '}';
				break;
			default:
				$template .= "<!--&IF(\$" . Fuzzer::pick($rebuildPlaceholderNames) . ",'" . $branch() . "','" . $branch() . "')-->";
		}
	}

	return [$template, $vals];
};

/** The reference rendering: choose IF branches by the value's truthiness, then substitute placeholders once. */
$rebuildReferenceRender = static function (string $template, array $vals): string {
	$text = preg_replace_callback("/<!--&IF\\(\\$([A-Z]),'([^']*)','([^']*)'\\)-->/", function (array $m) use ($vals): string {
		return ($vals['{$' . $m[1] . '}'] ?? false) ? $m[2] : $m[3];
	}, $template);

	return preg_replace_callback('/\{\$[^}]*\}/s', function (array $m) use ($vals): string {
		if (!array_key_exists($m[0], $vals)) {
			return $m[0];
		}
		$v = $vals[$m[0]];
		return (is_scalar($v) || $v === null) ? strval($v) : $m[0];
	}, $text);
};

// The compiler and the reference walk must agree on every template the grammar can produce:
// placeholders are substituted exactly once, IF picks by truthiness, and a value that looks like
// template syntax is emitted as it stands rather than compiled.
$fuzzer->target(
	'rebuild:templateEngine compile+render',
	fn(string $template, array $vals) => $rebuildRender->invoke($rebuildEngine, $rebuildCompile->invoke($rebuildEngine, $template), $vals),
	$rebuildTemplateCase,
	[
		['returns a string', fn($r) => is_string($r)],
		['matches the reference rendering', fn($r, $a) => $r === $rebuildReferenceRender($a[0], $a[1])],
		['no compiler markers leak', function ($r, $a) {
			foreach ($a[1] as $v) {
				if (is_string($v) && (str_contains($v, "\x02") || str_contains($v, "\x03"))) {
					return true;
				}
			}
			return !str_contains($r, "\x02") && !str_contains($r, "\x03");
		}],
	]
);

// The shipped THREAD block with hostile values for everything the thread renderer binds.
$rebuildThreadKeys = ['{$THREAD_UID}', '{$POST_OP_NUMBER}', '{$POST_OP_POST_UID}', '{$BOARD_UID}', '{$LAST_REPLY_TIME}', '{$LAST_BUMP_TIME}',
	'{$THREAD_CREATED_TIME}', '{$FORMATTED_THREAD_CREATED_TIME}', '{$MODULE_THREAD_CSS_CLASSES}', '{$MODULE_THREAD_HEADER}', '{$REPLIES}',
	'{$THREAD_OP}', '{$THREAD_CURRENT_PAGE}', '{$THREAD_TOTAL_PAGES}', '{$BOARD_THREAD_NAME}', '{$THREAD_NO}', '{$THREADNAV}'];

$fuzzer->target(
	'rebuild:templateEngine THREAD block',
	fn(array $vals) => $rebuildEngine->ParseBlock('THREAD', $vals),
	function () use ($rebuildThreadKeys, $rebuildTemplateValue, $rebuildText) {
		$vals = [];
		foreach ($rebuildThreadKeys as $key) {
			$vals[$key] = Fuzzer::bool() ? $rebuildText(30) : $rebuildTemplateValue();
		}
		return [$vals];
	},
	[
		['returns a non-empty string', fn($r) => is_string($r) && $r !== ''],
		['string values land verbatim', function ($r, $a) {
			foreach (['{$THREAD_OP}', '{$REPLIES}', '{$THREADNAV}', '{$THREAD_UID}', '{$THREAD_NO}'] as $key) {
				$v = $a[0][$key];
				if (is_string($v) && !str_contains($r, $v)) {
					return false;
				}
			}
			return true;
		}],
		['conditional values follow truthiness', function ($r, $a) {
			$v = $a[0]['{$MODULE_THREAD_HEADER}'];
			if (!is_string($v) || $v === '') {
				return true;
			}
			return $v ? str_contains($r, $v) : true;
		}],
		// A placeholder is substituted exactly once: the only copies of its token left in the page are
		// the ones carried inside the values themselves. A non-scalar value leaves it standing by design.
		['each placeholder substituted once', function ($r, $a) {
			foreach (['{$THREAD_OP}', '{$REPLIES}', '{$THREADNAV}', '{$BOARD_UID}', '{$THREAD_NO}', '{$THREAD_UID}'] as $key) {
				$v = $a[0][$key];
				if (!is_scalar($v) && $v !== null) {
					continue;
				}
				$inValues = 0;
				foreach ($a[0] as $other) {
					if (is_string($other)) {
						$inValues += substr_count($other, $key);
					}
				}
				if (substr_count($r, $key) !== $inValues) {
					return false;
				}
			}
			return true;
		}],
	]
);

// ---- Pagination and navigation ----------------------------------------------------

// The page a post sits on: never below 1, never decreasing along the thread, and the first
// full page of replies is page 1.
$fuzzer->target(
	'rebuild:getPageForPostPosition',
	fn(int $pos, int $perPage) => getPageForPostPosition($pos, $perPage),
	fn() => [Fuzzer::int(-5, 5000), Fuzzer::int(0, 300)],
	[
		['at least page 1', fn($r) => $r >= 1],
		['monotonic in position', fn($r, $a) => $a[1] === 0 || getPageForPostPosition($a[0] + 1, $a[1]) >= $r],
		['first page holds the first replies', fn($r, $a) => $a[1] === 0 || $a[0] > $a[1] || $r === 1],
	]
);

// The board pager: one entry per page, the current page marked once, and every link escaped.
$fuzzer->target(
	'rebuild:drawBoardPager',
	fn(int $perPage, int $total, string $url, int $page, int $static) => drawBoardPager($perPage, $total, $url, $page, $static, 'koko.php', 'index.html'),
	fn() => [Fuzzer::int(0, 40), Fuzzer::int(0, 600), Fuzzer::pick(['/b/', 'https://x.test/b/', '', '/a"b/', Fuzzer::nastyString(6)]), Fuzzer::int(-3, 60), Fuzzer::pick([-1, 0, 1, 3, 100])],
	[
		['returns a string', fn($r) => is_string($r)],
		['one entry per page', function ($r, $a) {
			[$pages] = validateAndClampPagination($a[0], $a[1], $a[3]);
			return substr_count($r, 'class="pagerPageLink"') === $pages;
		}],
		['exactly one selected page when there are pages', function ($r, $a) {
			[$pages] = validateAndClampPagination($a[0], $a[1], $a[3]);
			return substr_count($r, 'id="pagerSelectedPage"') === ($pages > 0 ? 1 : 0);
		}],
		['no unescaped quote inside an href', fn($r) => !preg_match('/href="[^"]*"[^ >]/', $r)],
		['first and last are inert at the ends', fn($r, $a) => str_contains($r, '>First<') || $a[3] > 1],
	]
);

/** A thread list entry as the rebuild hands it to the nav buttons: something with getThread(). */
$rebuildThreadEntry = static fn(int $board, int $op): object => new class($board, $op) {
	public function __construct(private int $board, private int $op) {}
	public function getThread(): Thread { return new Thread(['boardUID' => $this->board, 'post_op_number' => $this->op]); }
};

// The arrows point at the neighbours on the page and nowhere else.
$fuzzer->target(
	'rebuild:buildThreadNavButtons',
	fn(array $threads, int $i) => buildThreadNavButtons($threads, $i),
	function () use ($rebuildThreadEntry) {
		$threads = [];
		for ($n = Fuzzer::int(0, 6); $n > 0; $n--) {
			$threads[] = $rebuildThreadEntry(Fuzzer::int(0, 9), Fuzzer::int(0, 99999));
		}
		return [$threads, Fuzzer::int(-2, 8)];
	},
	[
		['empty off the page', fn($r, $a) => isset($a[0][$a[1]]) || $r === ''],
		['post form button on the page', fn($r, $a) => !isset($a[0][$a[1]]) || str_starts_with($r, '<a title="Go to post form" href="#postform">')],
		['up arrow iff a thread is above', fn($r, $a) => !isset($a[0][$a[1]]) || str_contains($r, 'Go to above thread') === ($a[1] > 0)],
		['down arrow iff a thread is below', fn($r, $a) => !isset($a[0][$a[1]]) || str_contains($r, 'Go to below thread') === ($a[1] < count($a[0]) - 1)],
		['arrows point at the neighbours', function ($r, $a) {
			if (!isset($a[0][$a[1]])) {
				return true;
			}
			foreach ([-1 => 'above', 1 => 'below'] as $offset => $word) {
				$neighbour = $a[0][$a[1] + $offset] ?? null;
				if ($neighbour !== null) {
					$t = $neighbour->getThread();
					if (!str_contains($r, 'Go to ' . $word . ' thread" href="#t' . $t->getBoardUID() . '_' . $t->getOpNumber() . '"')) {
						return false;
					}
				}
			}
			return true;
		}],
	]
);

// ---- Page finalisation -----------------------------------------------------------------

// The minifier only removes whitespace: tags, text and encoding survive. It is not a fixed point
// after one pass (collapsing a run can leave a newline against a tag that the earlier pattern
// would have taken), so what is asserted is that a second pass settles it.
$fuzzer->target(
	'rebuild:html_minify',
	fn(string $html) => html_minify($html),
	function () use ($rebuildText) {
		$html = '';
		for ($i = Fuzzer::int(0, 8); $i > 0; $i--) {
			$html .= Fuzzer::pick(['<div>', '</div>', "\n\t", '  ', ' ', "\r\n", '<pre>a  b</pre>', '<br>', $rebuildText(20), "\u{00A0}", "\u{3000}", '&nbsp;']);
		}
		return [$html];
	},
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$rebuildValidUtf8($a[0]) || $rebuildValidUtf8($r)],
		['never grows', fn($r, $a) => strlen($r) <= strlen($a[0])],
		['settles in two passes', fn($r) => html_minify(html_minify($r)) === html_minify($r)],
		['tag count unchanged', fn($r, $a) => substr_count($r, '<') === substr_count($a[0], '<') && substr_count($r, '>') === substr_count($a[0], '>')],
		['only whitespace removed', fn($r, $a) => preg_replace('/\s+/', '', $r) === preg_replace('/\s+/', '', $a[0])],
	]
);

// finalizePageData and getThreadPageTitle are private to the rebuilder and read only its config,
// so an instance without a constructor is enough to fuzz them.
$rebuildInstance = (new ReflectionClass(boardRebuilder::class))->newInstanceWithoutConstructor();
$rebuildConfigProp = new ReflectionProperty(boardRebuilder::class, 'config');
$rebuildConfigProp->setAccessible(true);
$rebuildConfigProp->setValue($rebuildInstance, ['MINIFY_HTML' => false, 'DEFAULT_NOCOMMENT' => 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!']);
$rebuildFinalize = new ReflectionMethod(boardRebuilder::class, 'finalizePageData');
$rebuildFinalize->setAccessible(true);
$rebuildPageTitle = new ReflectionMethod(boardRebuilder::class, 'getThreadPageTitle');
$rebuildPageTitle->setAccessible(true);

// A static page is finalised by blanking the post form's comment and email fields and dropping
// the reply highlight. Nothing else on the page may change, whether the fields sit on their own
// lines or share one with other markup, and whatever the comment field holds.
$fuzzer->target(
	'rebuild:finalizePageData',
	fn(string $page) => $rebuildFinalize->invoke($rebuildInstance, $page),
	function () use ($rebuildText) {
		$sep = fn(): string => Fuzzer::pick(["\n", '', ' ']);
		$oneLine = fn(int $len): string => str_replace(['"', "\n", "\r"], '', $rebuildText($len));
		$pieces = [
			'<textarea maxlength="2000" name="com" id="com" class="inputtext">' . $rebuildText(20) . '</textarea>',
			'<input maxlength="30" type="text" name="email" id="email" value="' . $oneLine(12) . '" class="inputtext">',
		];
		for ($i = Fuzzer::int(0, 5); $i > 0; $i--) {
			$pieces[] = Fuzzer::pick([
				'<div class="post reply replyhl" id="p1_2">' . $rebuildText(20) . '</div>',
				'<div class="post reply">' . $rebuildText(30) . '</div>',
				'<span>' . $rebuildText(20) . '</span>',
				'<textarea name="other">' . $rebuildText(10) . '</textarea>',
				'<input type="text" name="sub" id="sub" value="' . $oneLine(8) . '" class="inputtext">',
			]);
		}
		shuffle($pieces);
		return [implode($sep(), $pieces)];
	},
	[
		['comment textarea emptied', fn($r) => !preg_match('/id="com" class="inputtext">(?!<\/textarea>)/', $r)],
		['email value cleared', fn($r) => !preg_match('/id="email" value="(?!")/', $r)],
		['reply highlight dropped', fn($r) => !str_contains($r, 'replyhl')],
		['everything else untouched', function ($r, $a) {
			$expected = preg_replace([
				'/(id="com" class="inputtext">).*?(<\/textarea>)/s',
				'/(name="email" id="email" value=")[^"]*(" class="inputtext">)/',
				'/ ?replyhl/',
			], ['$1$2', '$1$2', ''], $a[0]);
			return str_replace(' ', '', $r) === str_replace(' ', '', $expected);
		}],
	]
);

// The <title> of a thread page: a bounded, escaped excerpt of the OP followed by the board title.
$fuzzer->target(
	'rebuild:getThreadPageTitle',
	function (string $sub, string $com, int $format, ?array $attachment, string $boardTitle) use ($rebuildInstance, $rebuildPageTitle) {
		$post = new Post(['sub' => $sub, 'com' => $com, 'text_format' => $format, 'post_uid' => 1]);
		if ($attachment !== null) {
			$post->addAttachment(1, $attachment);
		}
		return $rebuildPageTitle->invoke($rebuildInstance, $post, $boardTitle);
	},
	fn() => [
		Fuzzer::pick(['', $rebuildText(30), $rebuildText(4), ' ']),
		Fuzzer::pick(['', $rebuildText(60), 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!', '<br>x<b>y</b>', '&lt;&gt;&amp;']),
		Fuzzer::pick([0, 1, 2]),
		Fuzzer::bool() ? ['fileName' => $rebuildText(15), 'fileExtension' => Fuzzer::pick(['jpg', '', $rebuildText(4)])] : null,
		Fuzzer::pick(['Fuzz board', 'Board &amp; co', $rebuildText(10)]),
	],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$rebuildValidUtf8($a[0] . $a[1] . ($a[3]['fileName'] ?? '') . $a[4]) || $rebuildValidUtf8($r)],
		['ends with the board title', fn($r, $a) => str_ends_with($r, $a[4])],
		['excerpt is escaped', fn($r, $a) => !preg_match('/[<>"]/', substr($r, 0, strlen($r) - strlen($a[4])))],
		['excerpt is bounded', fn($r, $a) => mb_strlen(substr($r, 0, strlen($r) - strlen($a[4])), 'UTF-8') <= 128],
	]
);
