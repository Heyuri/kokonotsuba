<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\post\commentMarker;
use Kokonotsuba\post\textFormat;

use function Puchiko\strings\autoLink;
use function Puchiko\strings\newLinesToBreakLines;
use function Puchiko\strings\sanitizeStr;

/**
 * Turns a stored post field into render-ready HTML.
 *
 * Posts are stored as the poster typed them, so everything that makes a comment look like a
 * comment happens here: escaping, autolinking, line breaks, and the markers left behind by
 * whatever was decided at post time. Quote links, greentext and the module PostComment hooks
 * run after this, on the HTML it returns.
 *
 * Rows written before the plain-text switch carry textFormat::LEGACY_HTML and pass straight
 * through, because their columns already hold the HTML this would otherwise build.
 */
class commentFormatter {
	public function __construct(private readonly array $config) {}

	/**
	 * Build the HTML for a stored comment.
	 *
	 * @param string     $comment Comment as stored.
	 * @param textFormat $format  The post's stored text format.
	 */
	public function commentToHtml(string $comment, textFormat $format): string {
		// Legacy and raw-HTML comments are already markup; escaping them would show the tags.
		if ($format->commentIsHtml()) {
			return $this->expandMarkers($comment);
		}

		$html = sanitizeStr($comment);

		// Autolink before the line breaks go in: the URL pattern stops at whitespace, so a link
		// at the end of a line ends there rather than swallowing the <br> that follows it.
		if (!empty($this->config['AUTO_LINK'])) {
			$html = autoLink($html, $this->config['REF_URL'] ?? '');
		}

		$html = newLinesToBreakLines($html);

		// Any newline left over is dropped, so the stored comment's line structure is carried
		// entirely by <br> and the markup below never straddles a raw newline.
		$html = str_replace("\n", '', $html);

		return $this->expandMarkers($html);
	}

	/**
	 * Escape a stored name, email, subject or category for display.
	 *
	 * @param string     $value  Field as stored.
	 * @param textFormat $format The post's stored text format.
	 */
	public static function fieldToHtml(string $value, textFormat $format): string {
		return $format->fieldsAreHtml() ? $value : sanitizeStr($value);
	}

	/**
	 * The comment as readable plain text, for page titles, previews and other non-HTML uses.
	 *
	 * The result is unescaped text, so escape it at whatever it is being put into.
	 *
	 * @param string     $comment Comment as stored.
	 * @param textFormat $format  The post's stored text format.
	 */
	public static function commentToPlainText(string $comment, textFormat $format): string {
		if (!$format->commentIsHtml()) {
			return commentMarker::strip($comment);
		}

		// Legacy rows hold markup: drop the tags, and turn the breaks into spaces first so
		// words either side of one do not run together.
		$text = (string)preg_replace('#<br\s*/?>#i', ' ', $comment);

		return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * A stored name, email, subject or category as plain text.
	 *
	 * @param string     $value  Field as stored.
	 * @param textFormat $format The post's stored text format.
	 */
	public static function fieldToPlainText(string $value, textFormat $format): string {
		if (!$format->fieldsAreHtml()) {
			return $value;
		}

		return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/** Expand the markers this class owns; modules expand their own from PostComment. */
	private function expandMarkers(string $html): string {
		return commentMarker::expand($html, 'fortune', fn(string $payload): string => $this->renderFortune($payload));
	}

	/**
	 * Draw a fortune from its stored index. The colour is a function of the index, so a
	 * fortune keeps the same colour it was first shown in.
	 *
	 * @param string $payload Index into the FORTUNES config array.
	 */
	private function renderFortune(string $payload): string {
		$fortunes = $this->config['FORTUNES'] ?? [];
		$index = (int)$payload;

		if (!isset($fortunes[$index])) {
			return '';
		}

		$total = max(1, count($fortunes));
		$color = sprintf(
			'%02x%02x%02x',
			127 + 127 * sin(2 * M_PI * $index / $total),
			127 + 127 * sin(2 * M_PI * $index / $total + 2 / 3 * M_PI),
			127 + 127 * sin(2 * M_PI * $index / $total + 4 / 3 * M_PI)
		);

		return '<p class="fortune" style="color: #' . $color . ';">Your fortune: ' . sanitizeStr((string)$fortunes[$index]) . '</p>';
	}
}
