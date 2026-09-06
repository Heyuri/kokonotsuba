<?php

namespace Kokonotsuba\post;

/**
 * Recovers the text a poster typed from a comment stored as HTML.
 *
 * Rows written before the plain-text switch hold the finished markup: escaped input, <br> for
 * line breaks, autolink anchors, and whatever modules baked in. Most of that the renderer can
 * rebuild from the text alone, so it is simply unwound. The exceptions are a dice roll and a
 * fortune, which were decided once and cannot be re-rolled - those become markers, the same ones
 * a new post would carry.
 *
 * Used by Utilities/post-text-format-cli.php. Nothing does this at request time.
 */
final class legacyTextConverter {
	/** '<div class="rollContainer"><p class="roll">[NUMBERS: 4, 5]</p></div>' - an email roll. */
	private const EMAIL_ROLL = '#\s*<div class="rollContainer">\s*<p class="roll"[^>]*>\[NUMBERS?:\s*([\d,\s]+)\]</p>\s*</div>#i';

	/** '<span class="rollContainer">dice2d6=<span class="roll">4, 5 (9)</span></span>' */
	private const COMMENT_ROLL = '#\s*<span class="rollContainer">dice(\d+d\d+(?:[+-]\d+)?)=<span class="roll"[^>]*>([^<]*)</span></span>#i';

	/** '<p class="fortune" style="color: #aabbcc;">Your fortune: Great luck</p>' */
	private const FORTUNE = '#\s*<p class="fortune"[^>]*>Your fortune:\s*(.*?)</p>#is';

	private const BREAK_TAG = '#<br\s*/?>#i';

	/**
	 * Convert a stored comment to the plain text that renders back to it.
	 *
	 * @param string   $html     Comment as stored.
	 * @param string[] $fortunes The board's FORTUNES list, so a drawn fortune can be turned back
	 *                           into its index. Without it a fortune survives only as its text.
	 * @return string Text ready to store as textFormat::PLAIN_TEXT.
	 */
	public static function comment(string $html, array $fortunes = []): string {
		// First, so a marker the poster typed back then cannot be mistaken for a real roll once
		// the genuine ones are put in below.
		$html = commentMarker::strip($html);

		$html = self::convertRolls($html);
		$html = self::convertFortunes($html, $fortunes);

		$html = (string)preg_replace(self::BREAK_TAG, "\n", $html);

		return self::decode(strip_tags($html));
	}

	/**
	 * Convert a stored name, email, subject or category to plain text.
	 *
	 * Very old rows kept a poster's tripcode and capcode markup inside the name column. There is
	 * no text that renders back to that, so the markup is dropped and its text kept.
	 *
	 * @param string $html Field as stored.
	 */
	public static function field(string $html): string {
		return self::decode(strip_tags($html));
	}

	/** Turn both shapes of dice markup back into the markers the dice module now reads. */
	private static function convertRolls(string $html): string {
		$html = (string)preg_replace_callback(self::EMAIL_ROLL, function (array $m): string {
			$values = self::parseValues($m[1]);

			return $values === '' ? '' : commentMarker::make('diceemail', $values);
		}, $html);

		return (string)preg_replace_callback(self::COMMENT_ROLL, function (array $m): string {
			// The trailing '(total)' is recomputed from the notation at render time.
			$rolled = self::parseValues(explode('(', $m[2], 2)[0]);

			return $rolled === '' ? '' : "\n" . commentMarker::make('dice', $m[1] . ':' . $rolled);
		}, $html);
	}

	/**
	 * Turn a drawn fortune back into its index.
	 *
	 * A fortune that is no longer in the list, or a run with no list to hand, keeps its text so
	 * the post still reads the same - it just loses its colour.
	 *
	 * @param string[] $fortunes
	 */
	private static function convertFortunes(string $html, array $fortunes): string {
		return (string)preg_replace_callback(self::FORTUNE, function (array $m) use ($fortunes): string {
			$text = self::decode(strip_tags($m[1]));
			$index = array_search($text, array_map(fn($f): string => (string)$f, $fortunes), true);

			if ($index === false) {
				return "\nYour fortune: " . $text;
			}

			return "\n" . commentMarker::make('fortune', (string)$index);
		}, $html);
	}

	/**
	 * Normalize a comma-separated run of roll values.
	 *
	 * @return string '4,5', or '' when nothing numeric was in there.
	 */
	private static function parseValues(string $raw): string {
		$values = [];

		foreach (explode(',', $raw) as $value) {
			$value = trim($value);

			if ($value !== '' && ctype_digit($value)) {
				$values[] = $value;
			}
		}

		return implode(',', $values);
	}

	/** Undo the escaping that was applied at post time. */
	private static function decode(string $text): string {
		return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}
