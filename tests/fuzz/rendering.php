<?php

/**
 * Fuzz targets for post rendering.
 *
 * Included by tests/fuzz.php with $fuzzer in scope.
 *
 * A stored post goes through commentFormatter (escape, autolink, breaks, markers), then the quote
 * links and quote, then the module PostComment listeners. Every stage eats text a poster typed,
 * so each must survive hostile input without crashing, corrupting the encoding or letting markup
 * through. The legacy converter is fuzzed against the formatter, since one is the other unwound.
 */

use Koko\Tests\Framework\Fuzzer;
use Kokonotsuba\board\board;
use Kokonotsuba\post\commentMarker;
use Kokonotsuba\post\legacyTextConverter;
use Kokonotsuba\post\Post;
use Kokonotsuba\post\textFormat;
use Kokonotsuba\renderers\commentFormatter;
use Kokonotsuba\renderers\noscriptWidgetMenu;
use Kokonotsuba\Modules\emoji\emojiReplacer;
use Kokonotsuba\Modules\emotes\emoteReplacer;

use function Kokonotsuba\libraries\html\generateQuoteLinkHtml;
use function Kokonotsuba\libraries\html\quote_unkfunc;

require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/lib_post.php';
require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/html/helperHtmlFunctions.php';
require_once KOKO_TEST_ROOT . '/code/Kokonotsuba/libraries/html/postHtmlFunctions.php';
requireModuleFile('emoji/emojiReplacer.php');
requireModuleFile('emotes/emoteReplacer.php');

// The cross-board quote lookup reads the board list; an empty one resolves nothing.
if (!defined('GLOBAL_BOARD_ARRAY')) {
	define('GLOBAL_BOARD_ARRAY', []);
}

$renderFortunes = ['Great luck', 'Bad luck', 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!', 'a <b> & "c"'];
$renderConfig = ['AUTO_LINK' => true, 'REF_URL' => '', 'FORTUNES' => $renderFortunes];
$renderFormatter = new commentFormatter($renderConfig);
$renderFormatterNoLinks = new commentFormatter(['AUTO_LINK' => false, 'FORTUNES' => $renderFortunes]);

/** The control characters sanitizeStr() drops, so an oracle can drop them too. */
$renderControlChars = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x84\x86-\x9F\x{FDD0}-\x{FDDF}]/u';

/** Text as a poster might type it, salted with the shapes the renderer keys on. */
$renderText = static function (int $maxLen = 50): string {
	$parts = [];
	$n = Fuzzer::int(0, 6);
	for ($i = 0; $i < $n; $i++) {
		$parts[] = Fuzzer::pick([
			Fuzzer::nastyString($maxLen),
			Fuzzer::url(),
			'http://example.com/' . Fuzzer::nastyString(8),
			'https://a.test/x?y=1&z=2:astonish:',
			'>>' . Fuzzer::int(0, 200),
			'>>No.' . Fuzzer::int(0, 200),
			'>>>/' . Fuzzer::pick(['b', 'img.b', 'x-y_z', '']) . '/' . Fuzzer::int(0, 200),
			"\n>" . Fuzzer::nastyString(10),
			"\n＞" . Fuzzer::nastyString(10),
			"\n<" . Fuzzer::nastyString(10),
			"\n", "\r\n", "\r", "\n\n", "  ", "\t",
			commentMarker::make('fortune', (string)Fuzzer::int(-1, 5)),
			commentMarker::make('dice', Fuzzer::int(1, 3) . 'd' . Fuzzer::int(1, 6) . ':' . Fuzzer::int(1, 6)),
			'[[koko:fortune:' . Fuzzer::nastyString(6) . ']]',
			'[[koko:' . Fuzzer::nastyString(6) . ']]',
			':' . Fuzzer::pick(['smile', 'SMILE', 'astonish', 's', 'nope', '']) . ':',
			'<br>', '<script>', '&amp;', '&lt;br&gt;', '&#039;', '&gt;&gt;12',
			'😀🔥', '🎌',
		]);
	}
	$text = implode('', $parts);

	// one in eight is not valid UTF-8, as a corrupted row or a hostile client could be
	if (Fuzzer::int(0, 7) === 0) {
		$at = Fuzzer::int(0, strlen($text));
		$text = substr($text, 0, $at) . Fuzzer::pick(["\xff", "\xc3", "\xe6\x97", "\xed\xa0\x80"]) . substr($text, $at);
	}

	return $text;
};

$renderValidUtf8 = static fn(string $s): bool => mb_check_encoding($s, 'UTF-8');

/** A well-formed marker of a kind, as commentMarker's own pattern reads it. */
$renderMarkerPattern = static fn(string $kind): string => '/\[\[koko:' . $kind . ':[A-Za-z0-9,.:_+\- ]*\]\]/';

// ---- commentFormatter -------------------------------------------------------

// A plain-text comment: everything the poster typed is escaped, the only tags in the result are
// the ones the formatter itself builds, and its line structure is carried by <br> alone.
$fuzzer->target(
	'render:commentToHtml(plain)',
	fn(string $text, bool $links) => ($links ? $renderFormatter : $renderFormatterNoLinks)->commentToHtml($text, textFormat::PLAIN_TEXT),
	fn() => [$renderText(), Fuzzer::bool()],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r)],
		['no raw newline survives', fn($r) => !str_contains($r, "\n")],
		['no live <script tag', fn($r) => stripos($r, '<script') === false],
		['only formatter-built tags', function ($r) {
			preg_match_all('/<[^>]*>?/', $r, $m);
			foreach ($m[0] as $tag) {
				if (!preg_match('#^(<br>|<a href="[^"]*" rel="nofollow noreferrer" target="_blank">|</a>|<p class="fortune" style="color: \#[0-9a-f]{6};">|</p>)$#', $tag)) {
					return false;
				}
			}
			return true;
		}],
		['anchors balanced', fn($r) => substr_count($r, '<a ') === substr_count($r, '</a>')],
		['no well-formed fortune marker survives', fn($r) => !preg_match($renderMarkerPattern('fortune'), $r)],
		['markers of other kinds are left for their module', fn($r, $a) =>
			!preg_match($renderMarkerPattern('dice'), $a[0]) || preg_match($renderMarkerPattern('dice'), $r) === 1],
		['no raw control characters', fn($r) => !preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $r)],
	]
);

// HTML rows are already markup and pass through: apart from the fortune markers the formatter
// owns, the output is the input byte for byte.
$fuzzer->target(
	'render:commentToHtml(html)',
	fn(string $html, int $fmt) => $renderFormatter->commentToHtml($html, textFormat::from($fmt)),
	fn() => [$renderText(), Fuzzer::pick([textFormat::LEGACY_HTML->value, textFormat::RAW_HTML->value])],
	[
		['unchanged without a fortune marker', fn($r, $a) => preg_match($renderMarkerPattern('fortune'), $a[0]) === 1 || $r === $a[0]],
		['fortune markers expanded', fn($r) => !preg_match($renderMarkerPattern('fortune'), $r)],
		['never longer than input plus its fortunes', fn($r, $a) => strlen($r) <= strlen($a[0]) + 4 * 200],
	]
);

// The plain reading of a plain row is the row with its markers stripped, nothing else.
$fuzzer->target(
	'render:commentToPlainText(plain)',
	fn(string $text) => commentFormatter::commentToPlainText($text, textFormat::PLAIN_TEXT),
	fn() => [$renderText()],
	[
		['equals the marker-stripped input', fn($r, $a) => $r === commentMarker::strip($a[0])],
	]
);

// A legacy row is what the formatter would build from plain text, so reading it back as plain
// text must agree with the plain text it came from, up to whitespace and the characters the
// escaper drops.
$fuzzer->target(
	'render:commentToPlainText(legacy)',
	function (string $text) use ($renderFormatterNoLinks) {
		$html = $renderFormatterNoLinks->commentToHtml($text, textFormat::PLAIN_TEXT);
		return commentFormatter::commentToPlainText($html, textFormat::LEGACY_HTML);
	},
	fn() => [commentMarker::strip($renderText())],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r)],
		['no tag survives', fn($r, $a) => !$renderValidUtf8($a[0]) || !preg_match('/<(br|a|p)\b/i', $r) || preg_match('/<(br|a|p)\b/i', $a[0]) === 1],
		['agrees with the plain reading up to whitespace', function ($r, $a) use ($renderControlChars, $renderValidUtf8) {
			if (!$renderValidUtf8($a[0])) {
				return true;
			}
			$norm = fn(string $s): string => trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace($renderControlChars, '', $s)));
			return $norm($r) === $norm($a[0]);
		}],
	]
);

// A plain name/subject/email is escaped for display; nothing the poster typed reaches the page raw.
$fuzzer->target(
	'render:fieldToHtml',
	fn(string $v, int $fmt) => commentFormatter::fieldToHtml($v, textFormat::from($fmt)),
	fn() => [$renderText(20), Fuzzer::pick([0, 1, 2])],
	[
		['returns a string', fn($r) => is_string($r)],
		['plain fields carry no raw markup characters', fn($r, $a) => $a[1] === textFormat::LEGACY_HTML->value || !preg_match('/[<>"\']/', $r)],
		['legacy fields pass through', fn($r, $a) => $a[1] !== textFormat::LEGACY_HTML->value || $r === $a[0]],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r)],
	]
);

// ---- commentMarker ----------------------------------------------------------

// make() must build something its own pattern reads back, that escaping does not disturb, that
// strip() removes whole, and that expand() hands to the right handler with the payload it kept.
$fuzzer->target(
	'render:commentMarker roundTrip',
	function (string $kind, string $payload, string $around) {
		$marker = commentMarker::make($kind, $payload);
		$escaped = Puchiko\strings\sanitizeStr($around . $marker . $around);
		return [
			'marker' => $marker,
			'stripped' => commentMarker::strip($around . $marker . $around),
			'expanded' => commentMarker::expand($escaped, $kind, fn(string $p): string => '<X>' . $p . '</X>'),
			'other' => commentMarker::expand($marker, $kind . 'x', fn(string $p): string => 'WRONG'),
			'restrip' => commentMarker::strip(commentMarker::strip($around)),
			'once' => commentMarker::strip($around),
			// a typed marker, stripped as the post route strips it, then rendered as the formatter would
			'smuggled' => Puchiko\strings\sanitizeStr(commentMarker::strip($around . '[[koko:' . $kind . ':' . $payload . ']]' . $around)),
		];
	},
	fn() => [Fuzzer::pick(['dice', 'diceemail', 'fortune', 'a', 'kind_9']), Fuzzer::nastyString(20), Fuzzer::nastyString(12)],
	[
		['a typed marker never renders as genuine', fn($r, $a) => !preg_match($renderMarkerPattern($a[0]), str_replace("\r", '', $r['smuggled']))],
		['marker matches its own pattern', fn($r, $a) => preg_match($renderMarkerPattern($a[0]), $r['marker']) === 1],
		['strip removes the marker whole', fn($r, $a) => $r['stripped'] === commentMarker::strip($a[2] . $a[2])],
		['expand reaches the handler after escaping', fn($r) => str_contains($r['expanded'], '<X>') && !str_contains($r['expanded'], '[[koko:')],
		['payload kept only allowed characters', function ($r) {
			preg_match('/<X>(.*)<\/X>/s', $r['expanded'], $m);
			return isset($m[1]) && !preg_match('/[^A-Za-z0-9,.:_+\- ]/', $m[1]);
		}],
		['other kinds are left alone', fn($r) => $r['other'] === $r['marker']],
		['strip is idempotent', fn($r) => $r['restrip'] === $r['once']],
	]
);

// ---- legacyTextConverter ------------------------------------------------------

// Converting a legacy comment and rendering the result must give the same page the legacy row
// gave, which for a row the formatter itself built means render(convert(render(t))) === render(t).
// The input carries only markers a module would have written: a typed one is stripped at post
// time, and a fortune goes on its own line.
$fuzzer->target(
	'render:legacyTextConverter roundTrip',
	function (string $text, bool $links) use ($renderFormatter, $renderFormatterNoLinks, $renderFortunes) {
		$formatter = $links ? $renderFormatter : $renderFormatterNoLinks;
		$first = $formatter->commentToHtml($text, textFormat::PLAIN_TEXT);
		$back = legacyTextConverter::comment($first, $renderFortunes);
		return ['first' => $first, 'back' => $back, 'second' => $formatter->commentToHtml($back, textFormat::PLAIN_TEXT)];
	},
	function () use ($renderText, $renderFortunes) {
		$text = str_replace("\r", '', commentMarker::strip($renderText()));
		if (Fuzzer::bool()) {
			$text .= "\n" . commentMarker::make('fortune', (string)Fuzzer::int(0, count($renderFortunes) - 1));
		}
		return [$text, Fuzzer::bool()];
	},
	[
		['converted text is a string', fn($r) => is_string($r['back'])],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r['back'])],
		['no <br remains in the text', fn($r, $a) => !$renderValidUtf8($a[0]) || !str_contains($r['back'], '<br') || str_contains($a[0], '<br')],
		['renders back to the same html', fn($r, $a) => !$renderValidUtf8($a[0]) || $r['second'] === $r['first']],
	]
);

// Dice and fortune markup is the one thing that cannot be rebuilt from text, so it must come out
// as exactly the marker a new post would carry.
$fuzzer->target(
	'render:legacyTextConverter rolls',
	function (int $n, int $sides, array $values, int $fortune, string $pad) use ($renderFortunes) {
		$list = implode(', ', $values);
		$sum = array_sum($values);
		$fortuneText = htmlspecialchars($renderFortunes[$fortune] ?? 'Unknown fortune', ENT_QUOTES);
		$html = $pad
			. '<span class="rollContainer">dice' . $n . 'd' . $sides . '=<span class="roll">' . $list . ' (' . $sum . ')</span></span>'
			. $pad
			. '<div class="rollContainer"><p class="roll">[NUMBERS: ' . $list . ']</p></div>'
			. $pad
			. '<p class="fortune" style="color: #abcdef;">Your fortune: ' . $fortuneText . '</p>'
			. $pad;
		return legacyTextConverter::comment($html, $renderFortunes);
	},
	fn() => [
		Fuzzer::int(1, 9), Fuzzer::int(1, 100),
		array_map(fn() => Fuzzer::int(1, 100), range(1, Fuzzer::int(1, 5))),
		Fuzzer::int(0, 4),
		Fuzzer::pick(['', ' ', "\t", 'text', '<br>', '&amp;']),
	],
	[
		['comment roll becomes its marker', fn($r, $a) => str_contains($r, commentMarker::make('dice', $a[0] . 'd' . $a[1] . ':' . implode(',', $a[2])))],
		['email roll becomes its marker', fn($r, $a) => str_contains($r, commentMarker::make('diceemail', implode(',', $a[2])))],
		['known fortune becomes its index', fn($r, $a) => $a[3] > 3 || str_contains($r, commentMarker::make('fortune', (string)$a[3]))],
		['unknown fortune keeps its text', fn($r, $a) => $a[3] <= 3 || (str_contains($r, 'Your fortune: Unknown fortune') && !str_contains($r, '[[koko:fortune'))],
		['no markup remains', fn($r, $a) => $a[4] === '&amp;' || !preg_match('/<(span|div|p)\b/', $r)],
		['a break before a marker is not doubled', fn($r) => !preg_match('/\n\s*\n\[\[koko:(dice|fortune):/', $r)],
		['a break before a roll is kept', fn($r, $a) => $a[4] !== '<br>' || str_contains($r, "\n[[koko:dice:")],
	]
);

// Whatever the converter is fed, the only markers in its output are the kinds it makes itself.
$fuzzer->target(
	'render:legacyTextConverter hostile',
	fn(string $html) => legacyTextConverter::comment($html, $renderFortunes),
	fn() => [$renderText(80)],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r)],
		['only converter-made marker kinds', function ($r) {
			preg_match_all('/\[\[koko:([a-z][a-z0-9_]*):[A-Za-z0-9,.:_+\- ]*\]\]/', $r, $m);
			return !array_diff($m[1], ['dice', 'diceemail', 'fortune']);
		}],
	]
);

// ---- quote and quote links --------------------------------------------------

// quote_unkfunc only wraps lines in spans: the <br> structure is untouched, every span it opens
// is closed, and taking its spans out gives the input back (less the whitespace it trims from
// the front of a quoted line, which is by design).
$fuzzer->target(
	'render:quote_unkfunc',
	fn(string $html) => quote_unkfunc($html),
	fn() => [$renderFormatterNoLinks->commentToHtml($renderText(), textFormat::PLAIN_TEXT)],
	[
		['returns a string', fn($r) => is_string($r)],
		['<br> count unchanged', fn($r, $a) => substr_count($r, '<br>') === substr_count($a[0], '<br>')],
		['every span closed', fn($r) => substr_count($r, '<span class="unkfunc') === substr_count($r, '</span>')],
		['removing the spans restores the input', function ($r, $a) {
			$squash = fn(string $s): string => (string)preg_replace('/\s+/u', '', $s);
			return $squash(str_replace(['<span class="unkfunc">', '<span class="unkfunc2">', '</span>'], '', $r)) === $squash($a[0]);
		}],
		['only quote lines are wrapped', fn($r) => !preg_match('/<span class="unkfunc2?">(?!&gt;|＞|&lt;)/u', $r)],
	]
);

// A board that answers only what the quote link builder asks of it.
$renderQuoteBoard = new class extends board {
	public function __construct() {}
	public function getBoardUID(): int { return 7; }
	public function getConfigValue(string $key, $default = null, bool $throwOnMissing = false): mixed { return $default; }
	public function getBoardThreadURL(int $threadNumber, int $replyNumber = 0, bool $isQuoteRedirect = false, ?int $page = null, ?string $crossLink = null): string {
		return 'koko.php?res=' . $threadNumber . '&page=' . $page . '#p7_' . $replyNumber;
	}
};

$renderQuoteNumbers = [1, 2, 12, 123, 7, 70];

// Every >>N is wrapped in exactly one anchor, resolved ones link to their thread and page,
// unknown ones are struck out, anchors never nest, and stripping them restores the input.
$fuzzer->target(
	'render:generateQuoteLinkHtml',
	function (string $html, array $known, int $threadNo, int $repliesPerPage, bool $enabled) use ($renderQuoteBoard) {
		$post = new Post(['post_uid' => 99, 'com' => $html, 'no' => 500, 'boardUID' => 7]);
		$entries = [];
		foreach ($known as $no => $meta) {
			$entries[] = ['target_post' => [
				'no' => $no,
				'post_op_number' => $meta['op'],
				'post_position' => $meta['pos'],
				'post_uid' => $meta['uid'],
				'board_uid' => $meta['board'],
			]];
		}
		return generateQuoteLinkHtml([99 => $entries], $post, $threadNo, $enabled, $renderQuoteBoard, $repliesPerPage);
	},
	function () use ($renderText, $renderFormatterNoLinks, $renderQuoteNumbers) {
		$known = [];
		foreach ($renderQuoteNumbers as $no) {
			if (Fuzzer::bool()) {
				$known[$no] = ['op' => Fuzzer::pick([1, 12, $no]), 'pos' => Fuzzer::int(0, 500), 'uid' => Fuzzer::int(1, 9999), 'board' => Fuzzer::pick([7, 7, 8])];
			}
		}
		$text = $renderText(30);
		for ($i = Fuzzer::int(0, 4); $i > 0; $i--) {
			$text .= Fuzzer::pick(['>>', '＞＞', '>>No.', '>>>/b/', '>>>/img.b/', '>>>']) . Fuzzer::pick($renderQuoteNumbers) . Fuzzer::pick([' ', "\n", '', 'x']);
		}
		return [$renderFormatterNoLinks->commentToHtml($text, textFormat::PLAIN_TEXT), $known, Fuzzer::pick([1, 12, 999]), Fuzzer::int(1, 300), Fuzzer::int(0, 9) > 0];
	},
	[
		['returns a string', fn($r) => is_string($r)],
		['unchanged when the quote system is off', fn($r, $a) => $a[4] || $r === $a[0]],
		['anchors balanced', fn($r) => substr_count($r, '<a ') === substr_count($r, '</a>')],
		['anchors never nest', fn($r) => !preg_match('/<a [^>]*>(?:(?!<\/a>).)*<a /s', $r)],
		['stripping the anchors restores the input', fn($r, $a) =>
			preg_replace(['/<a [^>]*>/', '#</a>#', '#</?del>#'], '', $r) === $a[0]],
		['every >>N is wrapped', fn($r, $a) => !$a[4] || !preg_match('/(?<!>)(?:&gt;|＞){2}(?:No\.)?\d+/u', preg_replace('/<a [^>]*>(?:(?!<\/a>).)*<\/a>/s', '', $r))],
		['resolved links carry the page their position falls on', function ($r, $a) {
			if (!$a[4]) {
				return true;
			}
			foreach ($a[1] as $no => $meta) {
				if ($meta['board'] !== 7) {
					continue;
				}
				$page = $meta['pos'] <= 0 ? 1 : intdiv($meta['pos'] - 1, $a[3]) + 1;
				$expected = 'href="koko.php?res=' . $meta['op'] . '&amp;page=' . $page . '#p7_' . $no . '"';
				if (str_contains($a[0], '&gt;&gt;' . $no . ' ') && !str_contains($r, $expected)) {
					return false;
				}
			}
			return true;
		}],
		['unresolved links are struck out', fn($r) => !preg_match('/<a href="javascript:void\(0\);" class="quotelink[^"]*">(?!<del>)/', $r)],
		['the link text is only escaped text', fn($r) => !preg_match('/<a [^>]*>[^<]*[<>"][^<]*<\/a>/', preg_replace('#</?del>#', '', $r))],
	]
);

// ---- PostComment listeners ----------------------------------------------------------

$renderEmotes = ['smile' => 'smile.gif', 'astonish' => 'astonish.png', 's' => 's.gif', 'foo_bar' => 'fb.gif', 'SMILEY' => 'y.gif'];
$renderEmoteReplacer = new emoteReplacer($renderEmotes, 'https://static.test/emotes/');

/** The tags in a comment, in order, minus the ones the emote replacer adds. */
$renderTagsOf = static function (string $html): array {
	preg_match_all('/<[^>]+>/', $html, $m);
	return array_values(array_filter($m[0], fn(string $t): bool => !str_contains($t, 'class="emote"')));
};

// Emotes are swapped in one pass: markup is never touched, codes inside a tag are left alone,
// and a second pass finds nothing new to do.
$fuzzer->target(
	'render:emoteReplacer',
	fn(string $html) => $renderEmoteReplacer->replace($html),
	fn() => [$renderFormatterNoLinks->commentToHtml($renderText(), textFormat::PLAIN_TEXT)],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r)],
		['existing tags untouched, in order', fn($r, $a) => $renderTagsOf($r) === $renderTagsOf($a[0])],
		['idempotent', fn($r) => $renderEmoteReplacer->replace($r) === $r],
		['no live code left outside a tag', fn($r) => !preg_match('/:(smile|astonish|s|foo_bar|smiley):/i', preg_replace('/<[^>]+>/', '', $r))],
	]
);

$renderEmojis = ['😀' => 'grin', '🔥' => 'fire', '🎌' => 'flags'];
$renderEmojiReplacer = new emojiReplacer($renderEmojis, 'https://static.test/');

// Every mapped character becomes one image, no more and no fewer, and nothing else changes.
$fuzzer->target(
	'render:emojiReplacer',
	fn(string $html) => $renderEmojiReplacer->replace($html),
	fn() => [$renderFormatterNoLinks->commentToHtml($renderText(), textFormat::PLAIN_TEXT)],
	[
		['returns a string', fn($r) => is_string($r)],
		['valid UTF-8 out when valid in', fn($r, $a) => !$renderValidUtf8($a[0]) || $renderValidUtf8($r)],
		['one image per mapped character', function ($r, $a) use ($renderEmojis) {
			$expected = 0;
			foreach (array_keys($renderEmojis) as $char) {
				$expected += substr_count($a[0], $char);
			}
			return substr_count($r, '<img class="emoji"') === $expected;
		}],
		['removing the images restores the input', function ($r, $a) use ($renderEmojis) {
			$back = preg_replace_callback('/<img class="emoji" [^>]*alt="([^"]*)">/', fn(array $m): string => html_entity_decode($m[1], ENT_QUOTES), $r);
			return $back === $a[0];
		}],
	]
);

// ---- Widget menus and text format --------------------------------------------------

// The noscript row shows only entries a plain page load can follow, escapes what it shows and
// never emits a javascript: link.
$fuzzer->target(
	'render:noscriptWidgetMenu',
	fn(array $widgets) => noscriptWidgetMenu::render($widgets),
	function () {
		$widgets = [];
		for ($i = Fuzzer::int(0, 5); $i > 0; $i--) {
			$w = [];
			if (Fuzzer::int(0, 5) > 0) {
				$w['href'] = Fuzzer::pick(['#', '', ' ', 'javascript:void(0)', 'JavaScript:x', ' javascript:', Fuzzer::url(), 'koko.php?mode=x&' . Fuzzer::nastyString(8), Fuzzer::nastyString(12)]);
			}
			if (Fuzzer::int(0, 5) > 0) {
				$w['label'] = Fuzzer::pick(['', Fuzzer::nastyString(12), 'Reply']);
			}
			if (Fuzzer::bool()) {
				$w['params'] = ['target' => Fuzzer::pick(['_blank', '', Fuzzer::nastyString(6)]), 'x' => Fuzzer::nastyString(4)];
			}
			$w['action'] = Fuzzer::nastyString(5);
			$widgets[] = $w;
		}
		return [$widgets];
	},
	[
		['returns a string', fn($r) => is_string($r)],
		['well-formed link row', fn($r) => $r === '' || preg_match('#^\[<a href="[^"<>]*"( target="[^"<>]*")?>[^<>]*</a>\](?: \[<a href="[^"<>]*"( target="[^"<>]*")?>[^<>]*</a>\])*$#su', $r) === 1],
		['no javascript: href', fn($r) => !preg_match('/href="\s*javascript:/i', $r)],
		['one link per navigable labelled entry', function ($r, $a) {
			$expected = 0;
			foreach ($a[0] as $w) {
				$href = trim((string)($w['href'] ?? ''));
				if ($href !== '' && $href !== '#' && stripos($href, 'javascript:') !== 0 && (string)($w['label'] ?? '') !== '') {
					$expected++;
				}
			}
			return substr_count($r, '<a ') === $expected;
		}],
	]
);

// A stored format column can hold anything an old row or a bad import left there.
$fuzzer->target(
	'render:textFormat::fromStored',
	fn($v) => textFormat::fromStored($v),
	fn() => [Fuzzer::pick([0, 1, 2, 3, -1, '1', '2', '', 'abc', null, 1.9, PHP_INT_MAX, true, false, Fuzzer::nastyString(4)])],
	[
		['always a textFormat', fn($r) => $r instanceof textFormat],
		['reads the column as the integer it holds', fn($r, $a) => $r === (textFormat::tryFrom(is_array($a[0]) ? 0 : (int)$a[0]) ?? textFormat::LEGACY_HTML)],
	]
);
