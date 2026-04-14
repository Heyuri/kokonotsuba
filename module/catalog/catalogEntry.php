<?php

namespace Kokonotsuba\Modules\catalog;

use function Puchiko\strings\sanitizeStr;

/**
 * DTO representing a single catalog entry for template rendering.
 * 
 * Contains pre-processed data ready for display: thread URL, thumbnail URL,
 * subject, comment, and reply count.
 */
class catalogEntry {
	public function __construct(
		private readonly int $threadNumber,
		private readonly string $threadUrl,
		private readonly string $thumbnailUrl,
		private readonly int $thumbWidth,
		private readonly string $subject,
		private readonly string $comment,
		private readonly int $replyCount,
		private readonly string $postInfoExtra,
		private readonly bool $isSticky,
	) {}

	public function getThreadNumber(): int { return $this->threadNumber; }
	public function getThreadUrl(): string { return $this->threadUrl; }
	public function getThumbnailUrl(): string { return $this->thumbnailUrl; }
	public function getThumbWidth(): int { return $this->thumbWidth; }
	public function getSubject(): string { return $this->subject; }
	public function getComment(): string { return $this->comment; }
	public function getReplyCount(): int { return $this->replyCount; }
	public function getPostInfoExtra(): string { return $this->postInfoExtra; }
	public function isSticky(): bool { return $this->isSticky; }

	/**
	 * Convert to associative array for JSON serialization (used by JS sort endpoint).
	 *
	 * @return array Catalog entry data for JSON output.
	 */
	public function toJson(): array {
		return [
			'no' => $this->threadNumber,
			'url' => $this->threadUrl,
			'thumb' => $this->thumbnailUrl,
			'tw' => $this->thumbWidth,
			'sub' => $this->subject,
			'com' => $this->comment,
			'r' => $this->replyCount,
			'extra' => $this->postInfoExtra,
			'sticky' => $this->isSticky,
		];
	}

	/**
	 * Convert to template row for the template engine's FOREACH directive.
	 *
	 * @param string $repliesIconUrl URL of the replies icon image.
	 * @return array Template placeholder values.
	 */
	public function toTemplateRow(string $repliesIconUrl): array {
		// Build the thumbnail HTML
		$thumbHtml = '<img src="' . sanitizeStr($this->thumbnailUrl) . '"'
			. ($this->thumbWidth ? ' width="' . $this->thumbWidth . '"' : '')
			. ' class="thumb" alt="Thumbnail">';

		return [
			'{$THREAD_URL}' => $this->threadUrl,
			'{$THUMB_HTML}' => $thumbHtml,
			'{$SUBJECT}' => $this->subject,
			'{$POST_INFO_EXTRA}' => $this->postInfoExtra,
			'{$REPLY_COUNT}' => $this->replyCount,
			'{$REPLIES_ICON}' => sanitizeStr($repliesIconUrl),
			'{$COMMENT}' => $this->comment,
			'{$IS_STICKY}' => $this->isSticky,
		];
	}
}
