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
		return (string)preg_replace(self::PATTERN, '', $text);
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
