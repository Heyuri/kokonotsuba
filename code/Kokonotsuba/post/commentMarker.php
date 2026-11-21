<?php

namespace Kokonotsuba\post;

/**
 * Placeholders for content decided when a post is made but drawn when it is read.
 *
 * A dice roll and a fortune are rolled once, at post time, yet their markup belongs to the
 * renderer. Both store a marker like [[koko:dice:2d6:4,5]] in the plain-text comment; a
 * render-time listener swaps it for HTML. Payloads are restricted to characters that survive
 * htmlspecialchars() untouched, so a marker still matches after the comment is escaped.
 */
final class commentMarker {
	/** Characters a payload may contain; anything else would change shape when escaped. */
	private const PAYLOAD_ALLOWED = '/[^A-Za-z0-9,.:_+\- ]/';

	private const PATTERN = '/\[\[koko:([a-z][a-z0-9_]*):([A-Za-z0-9,.:_+\- ]*)\]\]/';

	/**
	 * What strip() removes: a marker whose payload also holds characters the renderer drops
	 * (sanitizeStr's control characters, and the CR commentToHtml discards). Raw input is
	 * stripped before it is escaped, so a payload smuggling one of those past the strict pattern
	 * would turn into a genuine marker at render time. Matched bytewise, since raw input need
	 * not be valid UTF-8: C1 controls and the U+FDD0 block are spelled as their UTF-8 bytes.
	 */
	private const STRIP_PATTERN = '/\[\[koko:[a-z][a-z0-9_]*:(?:[A-Za-z0-9,.:_+\- \x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x84\x86-\x9F]|\xEF\xB7[\x90-\x9F])*\]\]/';

	/**
	 * Build a marker for the given kind.
	 *
	 * @param string $kind    Marker kind, e.g. 'dice'.
	 * @param string $payload Data for the renderer; disallowed characters are dropped.
	 */
	public static function make(string $kind, string $payload): string {
		return '[[koko:' . $kind . ':' . preg_replace(self::PAYLOAD_ALLOWED, '', $payload) . ']]';
	}

	/**
	 * Remove every marker from text.
	 *
	 * Called on raw input before any module adds its own, so a poster cannot type a marker
	 * and have it rendered as a genuine roll.
	 *
	 * @param string $text Text to strip.
	 */
	public static function strip(string $text): string {
		return (string)preg_replace(self::STRIP_PATTERN, '', $text);
	}

	/**
	 * Keep only the markers that already were in the original text.
	 *
	 * For an edit: markers may be moved or deleted, but one that was changed or typed in is
	 * dropped. Each original marker is kept at most as many times as it appeared.
	 *
	 * @param string $text     Edited text.
	 * @param string $original Text before the edit.
	 * @return string The edited text with unknown markers removed.
	 */
	public static function retainOriginal(string $text, string $original): string {
		// How many times each original marker may still be used
		$allowance = self::countMarkers($original);

		// One pass over every marker-shaped token, strict or smuggled
		return (string)preg_replace_callback(
			self::STRIP_PATTERN,
			function (array $m) use (&$allowance): string {
				return self::takeAllowance($allowance, $m[0]) ? $m[0] : '';
			},
			$text
		);
	}

	/**
	 * Count each distinct marker in text.
	 *
	 * @param string $text Text to scan.
	 * @return array<string, int> Marker => occurrences.
	 */
	private static function countMarkers(string $text): array {
		// Every genuine marker in the text
		preg_match_all(self::PATTERN, $text, $matches);

		return array_count_values($matches[0]);
	}

	/**
	 * Use up one occurrence of a marker, if any are left.
	 *
	 * @param array<string, int> &$allowance Remaining occurrences per marker.
	 * @param string $marker The marker found.
	 * @return bool Whether it may be kept.
	 */
	private static function takeAllowance(array &$allowance, string $marker): bool {
		// None left, or never there
		if (($allowance[$marker] ?? 0) < 1) {
			return false;
		}

		// Spend one
		$allowance[$marker]--;

		return true;
	}

	/**
	 * Replace markers of one kind with the handler's output.
	 *
	 * @param string   $html    Comment HTML being assembled.
	 * @param string   $kind    Kind to expand; markers of other kinds are left alone.
	 * @param callable $handler fn(string $payload): string
	 */
	public static function expand(string $html, string $kind, callable $handler): string {
		if (!str_contains($html, '[[koko:' . $kind . ':')) {
			return $html;
		}

		return (string)preg_replace_callback(
			self::PATTERN,
			fn(array $m): string => $m[1] === $kind ? $handler($m[2]) : $m[0],
			$html
		);
	}
}
