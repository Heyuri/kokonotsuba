<?php

namespace Kokonotsuba\quote_link;

use Kokonotsuba\post\textFormat;

/**
 * The rules a ">some text" or ">file.jpg" quote is matched by, kept pure so they can be tested
 * without a database and compared against the page script (static/js/quoteLookup.js), which
 * applies the same rules to the posts it can see.
 */
final class textQuoteMatcher {
	/** Longest needle accepted, in characters. The page script truncates to the same length. */
	public const MAX_NEEDLE_LENGTH = 300;

	/** Longest post number worth looking up. */
	private const MAX_NUMBER_DIGITS = 10;

	/**
	 * Trim a needle and refuse what cannot match anything: empty, overlong, broken UTF-8
	 * or carrying control characters (a quote is a single line).
	 */
	public static function normalizeNeedle(string $raw): ?string {
		if (!mb_check_encoding($raw, 'UTF-8')) {
			return null;
		}

		$needle = preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $raw);

		if ($needle === null || $needle === '' || mb_strlen($needle, 'UTF-8') > self::MAX_NEEDLE_LENGTH) {
			return null;
		}

		if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $needle)) {
			return null;
		}

		return $needle;
	}

	/** The post number a ">123" or ">No.123" needle names, or null when it is text. */
	public static function postNumber(string $needle): ?int {
		if (!preg_match('/^(?:No\. ?)?(\d+)$/', $needle, $match)) {
			return null;
		}

		$digits = ltrim($match[1], '0');

		// a number too long to exist still counts as one, so it never falls through to text
		if ($digits === '' || strlen($digits) > self::MAX_NUMBER_DIGITS) {
			return 0;
		}

		return (int)$digits;
	}

	/**
	 * Split a needle that could be a file name into the stored name and extension.
	 *
	 * @return array{0: string, 1: string}|null
	 */
	public static function splitFileName(string $needle): ?array {
		$dot = strrpos($needle, '.');

		if ($dot === false || $dot === 0) {
			return null;
		}

		$extension = substr($needle, $dot + 1);

		if (!preg_match('/^[A-Za-z0-9]{1,16}$/', $extension)) {
			return null;
		}

		return [substr($needle, 0, $dot), $extension];
	}

	/** The file name as the attachment bar shows it. */
	public static function displayedFileName(string $fileName, string $extension): string {
		return $fileName . (str_contains($extension, '.') ? '' : '.') . $extension;
	}

	/** A LIKE pattern finding the needle anywhere, with the wildcards in it escaped. */
	public static function likePattern(string $needle): string {
		return '%' . addcslashes($needle, '\\%_') . '%';
	}

	/** The needle as a LEGACY_HTML comment holds it. */
	public static function legacyNeedle(string $needle): string {
		return htmlspecialchars($needle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** The text a reader sees for a stored comment, one line per line break. */
	public static function visibleText(string $stored, textFormat $format): string {
		if (!$format->commentIsHtml()) {
			return $stored;
		}

		$text = preg_replace('/<br\s*\/?>/i', "\n", $stored) ?? $stored;

		return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Whether a post's comment is the source of a text quote.
	 *
	 * A quote is one line, so a needle has to sit inside one line of the comment: text that only
	 * appears across a line break is not what the quoter copied. A ">text" quote matches the
	 * post's own lines, so a post that merely repeats the quote is not its source; a ">>text"
	 * quote is a quote of a quote, so the post has to have a quote line of its own and any of
	 * its lines may carry the needle.
	 */
	public static function commentMatches(string $stored, textFormat $format, string $needle, bool $quoted): bool {
		if ($needle === '') {
			return false;
		}

		$hasQuoteLine = false;
		$matched = false;

		foreach (self::lines($stored, $format) as $line) {
			$isQuoteLine = self::isQuoteLine($line);

			if ($isQuoteLine) {
				$hasQuoteLine = true;
			}

			if (($quoted || !$isQuoteLine) && str_contains($line, $needle)) {
				$matched = true;
			}
		}

		return $quoted ? ($hasQuoteLine && $matched) : $matched;
	}

	/**
	 * The lines a reader sees, which is what a quote is compared against.
	 *
	 * @return string[]
	 */
	public static function lines(string $stored, textFormat $format): array {
		return preg_split('/\r\n|\r|\n/', self::visibleText($stored, $format)) ?: [];
	}

	/** Whether any line of the comment is drawn as a quote. */
	public static function hasQuoteLine(string $stored, textFormat $format): bool {
		foreach (self::lines($stored, $format) as $line) {
			if (self::isQuoteLine($line)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the renderer would draw this line as a quote.
	 *
	 * The leading whitespace it tolerates is the set JavaScript's own trim() removes, so the
	 * page script reaches the same verdict on the same line.
	 */
	public static function isQuoteLine(string $line): bool {
		$line = preg_replace('/^[\s\p{Z}\x{FEFF}]+/u', '', $line) ?? ltrim($line);

		return str_starts_with($line, '>') || str_starts_with($line, '＞');
	}
}
