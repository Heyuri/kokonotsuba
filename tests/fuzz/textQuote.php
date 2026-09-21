<?php

/**
 * Fuzz targets for text quote lookups (the post API's pageName=quote).
 *
 * Included by tests/fuzz.php with $fuzzer in scope.
 *
 * The needle is whatever a visitor puts in a URL, so the normaliser and the LIKE escaping must
 * hold against anything; the matcher reads comments posters typed; and the resolver is checked
 * against a brute-force reading of the same thread, which is what pins "nearest earlier post".
 */

use Koko\Tests\Framework\Fuzzer;
use Koko\Tests\Framework\InMemoryTextQuoteRepository;
use Kokonotsuba\post\textFormat;
use Kokonotsuba\quote_link\textQuoteMatcher;
use Kokonotsuba\quote_link\textQuoteResolver;

$quoteValidUtf8 = static fn(string $s): bool => mb_check_encoding($s, 'UTF-8');

/** Words from a tiny vocabulary, so needles really do occur in the threads below. */
$quoteWords = static function (int $max = 4): string {
	$out = [];
	for ($i = Fuzzer::int(0, $max); $i > 0; $i--) {
		$out[] = Fuzzer::pick(['cat', 'Cat', 'dog', 'the', '草', 'a&b', '<3', '100%', 'snake_case', 'file.png', '12', 'No.3', "it's"]);
	}
	return implode(Fuzzer::pick([' ', '  ', '']), $out);
};

$quoteComment = static function () use ($quoteWords): string {
	$lines = [];
	for ($i = Fuzzer::int(0, 4); $i > 0; $i--) {
		$lines[] = Fuzzer::pick(['', '', '>', '>>', '＞', ' >']) . (Fuzzer::int(0, 9) === 0 ? Fuzzer::nastyString(12) : $quoteWords());
	}
	return implode(Fuzzer::pick(["\n", "\r\n"]), $lines);
};

$fuzzer->target(
	'textQuote:normalizeNeedle',
	fn(string $raw) => textQuoteMatcher::normalizeNeedle($raw),
	fn() => [Fuzzer::pick([Fuzzer::nastyString(60), str_repeat(Fuzzer::nastyString(8), Fuzzer::int(1, 80)), "  \u{3000}" . Fuzzer::nastyString(10) . "\t "])],
	[
		['null or a string', fn($r) => $r === null || is_string($r)],
		['valid UTF-8', fn($r) => $r === null || $quoteValidUtf8($r)],
		['never empty', fn($r) => $r !== ''],
		['within the length limit', fn($r) => $r === null || mb_strlen($r, 'UTF-8') <= textQuoteMatcher::MAX_NEEDLE_LENGTH],
		['single line, no control characters', fn($r) => $r === null || !preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $r)],
		['idempotent', fn($r) => $r === null || textQuoteMatcher::normalizeNeedle($r) === $r],
		['broken input is refused, not repaired', fn($r, $a) => $quoteValidUtf8($a[0]) || $r === null],
	]
);

$fuzzer->target(
	'textQuote:likePattern',
	fn(string $needle) => textQuoteMatcher::likePattern($needle),
	fn() => [Fuzzer::pick([Fuzzer::nastyString(30), '%', '_', '\\', '\\%', '%_\\' . Fuzzer::nastyString(5), $quoteWords()])],
	[
		['wrapped in wildcards', fn($r) => str_starts_with($r, '%') && str_ends_with($r, '%')],
		['no bare wildcard inside', fn($r) => !preg_match('/(?<!\\\\)(?:\\\\\\\\)*[%_]/', substr($r, 1, -1))],
		['unescapes to the needle', fn($r, $a) => preg_replace('/\\\\([\\\\%_])/', '$1', substr($r, 1, -1)) === $a[0]],
	]
);

$fuzzer->target(
	'textQuote:postNumber+splitFileName',
	fn(string $needle) => [textQuoteMatcher::postNumber($needle), textQuoteMatcher::splitFileName($needle)],
	fn() => [Fuzzer::pick([Fuzzer::nastyString(20), 'No.' . Fuzzer::int(0, 99999), str_repeat('9', Fuzzer::int(1, 40)), Fuzzer::nastyString(6) . '.' . Fuzzer::pick(['jpg', 'PNG', 'tar.gz', '', 'x y', str_repeat('a', 20)])])],
	[
		['number is null or a sane int', fn($r) => $r[0] === null || (is_int($r[0]) && $r[0] >= 0 && $r[0] <= 9999999999)],
		['a split name joins back to the needle', fn($r, $a) => $r[1] === null || textQuoteMatcher::displayedFileName($r[1][0], $r[1][1]) === $a[0]],
		['a split name has a name and a plain extension', fn($r) => $r[1] === null || ($r[1][0] !== '' && preg_match('/^[A-Za-z0-9]{1,16}$/', $r[1][1]))],
	]
);

$fuzzer->target(
	'textQuote:commentMatches',
	fn(string $comment, int $format, string $needle, bool $quoted) => textQuoteMatcher::commentMatches($comment, textFormat::from($format), $needle, $quoted),
	fn() => [
		Fuzzer::int(0, 3) === 0 ? Fuzzer::nastyString(80) : $quoteComment(),
		Fuzzer::pick([0, 1, 2]),
		Fuzzer::pick([$quoteWords(2), Fuzzer::nastyString(6), '']),
		Fuzzer::bool(),
	],
	[
		['returns a bool', fn($r) => is_bool($r)],
		['a match sits inside one line', function ($r, $a) {
			if (!$r) {
				return true;
			}
			foreach (textQuoteMatcher::lines($a[0], textFormat::from($a[1])) as $line) {
				if (str_contains($line, $a[2])) {
					return true;
				}
			}
			return false;
		}],
		['an empty needle matches nothing', fn($r, $a) => $a[2] !== '' || $r === false],
		['a quote of a quote needs a quote line', fn($r, $a) => !($r && $a[3]) || textQuoteMatcher::hasQuoteLine($a[0], textFormat::from($a[1]))],
		['a comment of nothing but quotes is never a plain source', function ($r, $a) {
			if ($a[3] || $a[1] !== 1 || $a[2] === '') {
				return true;
			}
			foreach (preg_split('/\r\n|\r|\n/', $a[0]) as $line) {
				if (!textQuoteMatcher::isQuoteLine($line)) {
					return true;
				}
			}
			return $r === false;
		}],
	]
);

/** A small random thread: shuffled uids past the OP's, deletions, files, both text formats. */
$quoteThread = static function () use ($quoteComment): array {
	$posts = [];
	$uid = Fuzzer::int(1, 50);
	$opUid = Fuzzer::int(0, 4) === 0 ? 9999 : $uid;   // a merged thread's OP can outrank its replies

	for ($i = 0, $n = Fuzzer::int(2, 14); $i < $n; $i++) {
		$legacy = Fuzzer::int(0, 3) === 0;
		$comment = $quoteComment();
		$posts[] = [
			'post_uid' => $i === 0 ? $opUid : ($uid += Fuzzer::int(1, 5)),
			'no' => 100 + $i,
			'is_op' => $i === 0,
			'com' => $legacy ? nl2br(htmlspecialchars($comment, ENT_QUOTES), false) : $comment,
			'text_format' => $legacy ? 0 : 1,
			'files' => Fuzzer::int(0, 2) === 0 ? [[Fuzzer::pick(['file', 'File', 'cat']), Fuzzer::pick(['png', '.png', 'jpg'])]] : [],
			'deleted' => $i > 0 && Fuzzer::int(0, 5) === 0,
		];
	}

	return $posts;
};

/** The rules read the long way: walk the thread backwards from the quoting post. */
$quoteOracle = static function (array $posts, int $beforeUid, string $needle, bool $quoted): ?int {
	$quotingPost = null;

	foreach ($posts as $post) {
		if ($post['post_uid'] === $beforeUid) {
			$quotingPost = $post;
		}
	}

	// a lookup from a post that is not a reply of this thread is answered with nothing
	if ($quotingPost === null || $quotingPost['is_op']) {
		return null;
	}

	$earlier = array_filter($posts, static fn(array $p): bool =>
		!$p['deleted'] && $p['post_uid'] !== $beforeUid && ($p['is_op'] || $p['post_uid'] < $beforeUid));
	usort($earlier, static fn(array $a, array $b): int => [$a['is_op'], $b['post_uid']] <=> [$b['is_op'], $a['post_uid']]);

	$number = textQuoteMatcher::postNumber($needle);

	foreach ($earlier as $post) {
		$format = textFormat::from($post['text_format']);

		if ($number !== null) {
			if ($number > 0 && $post['no'] === $number) {
				return $post['post_uid'];
			}
			continue;
		}

		if ($quoted && !textQuoteMatcher::hasQuoteLine($post['com'], $format)) {
			continue;
		}

		if (textQuoteMatcher::commentMatches($post['com'], $format, $needle, $quoted)) {
			return $post['post_uid'];
		}

		foreach ($post['files'] as [$name, $ext]) {
			if (textQuoteMatcher::displayedFileName($name, $ext) === $needle) {
				return $post['post_uid'];
			}
		}
	}

	return null;
};

$fuzzer->target(
	'textQuote:resolver vs brute force',
	fn(array $posts, int $beforeUid, string $needle, bool $quoted) =>
		(new textQuoteResolver(new InMemoryTextQuoteRepository($posts)))->resolve('t', $beforeUid, $needle, $quoted),
	function () use ($quoteThread, $quoteWords) {
		$posts = $quoteThread();
		$needle = textQuoteMatcher::normalizeNeedle(Fuzzer::pick([$quoteWords(2), $quoteWords(1), 'file.png', 'File.png', 'cat.jpg', (string)Fuzzer::int(99, 115), 'No.' . Fuzzer::int(99, 115)]));
		// the lookup is always made from a post of the thread, as the page script sends it
		return [$posts, Fuzzer::pick(array_column($posts, 'post_uid')), $needle ?? 'cat', Fuzzer::bool()];
	},
	[
		['agrees with the brute-force reading', fn($r, $a) => $r === $quoteOracle(...$a)],
		['never the quoting post, never a deleted one', function ($r, $a) {
			foreach ($a[0] as $post) {
				if ($post['post_uid'] === $r && ($post['deleted'] || $r === $a[1])) {
					return false;
				}
			}
			return true;
		}],
	]
);
