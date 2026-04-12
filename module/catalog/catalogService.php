<?php

namespace Kokonotsuba\Modules\catalog;

use Kokonotsuba\board\board;

use function Kokonotsuba\libraries\html\quote_unkfunc;
use function Kokonotsuba\libraries\resolveThumbnailDisplayUrl;

/**
 * Service for building catalog entry data from raw repository results.
 *
 * Handles thumbnail resolution, comment processing, and pagination math.
 */
class catalogService {
	public function __construct(
		private readonly catalogRepository $catalogRepository,
	) {}

	/**
	 * Fetch a page of catalog entries for the given board.
	 *
	 * @param board  $board     Board to fetch entries from.
	 * @param int    $page      Zero-based page index.
	 * @param int    $perPage   Entries per page.
	 * @param string $sortBy    Sort column: 'bump' or 'time'.
	 * @return catalogEntry[] Array of catalog entry DTOs.
	 */
	public function getCatalogEntries(board $board, int $page, int $perPage, string $sortBy = 'bump'): array {
		$boardUID = $board->getBoardUID();
		$offset = $page * $perPage;

		// Map user-facing sort names to DB columns
		$orderColumn = $this->resolveSortColumn($sortBy);

		$rows = $this->catalogRepository->getCatalogEntries(
			$boardUID,
			$perPage,
			$offset,
			$orderColumn
		);

		return $this->buildEntries($rows, $board);
	}

	/**
	 * Fetch ALL catalog entries for JSON output (used by JS sort).
	 *
	 * Returns a lightweight array suitable for JSON encoding so the
	 * client can re-sort without a full page reload.
	 *
	 * @param board  $board   Board to fetch entries from.
	 * @param string $sortBy  Sort column: 'bump' or 'time'.
	 * @return array Array of JSON-ready catalog entry arrays.
	 */
	public function getCatalogEntriesAsJson(board $board, string $sortBy = 'bump'): array {
		$boardUID = $board->getBoardUID();
		$orderColumn = $this->resolveSortColumn($sortBy);

		// Fetch all entries (no pagination for JSON sort)
		$rows = $this->catalogRepository->getCatalogEntries(
			$boardUID,
			0, // 0 = no limit
			0,
			$orderColumn
		);

		$entries = $this->buildEntries($rows, $board);

		return array_map(fn(catalogEntry $e) => $e->toJson(), $entries);
	}

	/**
	 * Count total visible threads for pagination.
	 *
	 * @param board $board Board to count threads from.
	 * @return int Thread count.
	 */
	public function countEntries(board $board): int {
		return $this->catalogRepository->countCatalogEntries($board->getBoardUID());
	}

	/**
	 * Map user-facing sort key to database column name.
	 *
	 * @param string $sortBy 'bump' or 'time'.
	 * @return string Database column name.
	 */
	private function resolveSortColumn(string $sortBy): string {
		return match ($sortBy) {
			'time' => 'thread_created_time',
			default => 'last_bump_time',
		};
	}

	/**
	 * Build catalogEntry DTOs from raw database rows.
	 *
	 * @param array $rows  Raw rows from catalogRepository.
	 * @param board $board Board object for URL/path resolution.
	 * @return catalogEntry[] Array of catalog entry DTOs.
	 */
	private function buildEntries(array $rows, board $board): array {
		$entries = [];

		foreach ($rows as $row) {
			$threadNumber = (int) $row['post_op_number'];
			$threadUrl = $board->getBoardThreadURL($threadNumber);

			// Resolve thumbnail URL with type-based fallbacks
			$thumbnailUrl = $this->resolveThumbnailUrl($row, $board);
			$thumbWidth = $thumbnailUrl ? (int) ($row['file_thumb_width'] ?? 0) : 0;

			// Process the comment text for display
			$comment = quote_unkfunc((string) ($row['op_comment'] ?? ''));
			$subject = (string) ($row['op_subject'] ?? '');

			$replyCount = max(0, (int) ($row['reply_count'] ?? 0));

			$entries[] = new catalogEntry(
				threadNumber: $threadNumber,
				threadUrl: $threadUrl,
				thumbnailUrl: $thumbnailUrl,
				thumbWidth: $thumbWidth,
				subject: $subject,
				comment: $comment,
				replyCount: $replyCount,
				postInfoExtra: '',
				isSticky: (bool) ($row['is_sticky'] ?? false),
			);
		}

		return $entries;
	}

	/**
	 * Resolve the thumbnail URL for a catalog entry.
	 *
	 * Uses the shared resolveThumbnailDisplayUrl() helper which handles all
	 * fallback cases: nofile.gif, nothumb.gif, SWF/audio/archive placeholders.
	 *
	 * @param array $row   Raw database row with file columns.
	 * @param board $board Board for path/config resolution.
	 * @return string Thumbnail URL to display.
	 */
	private function resolveThumbnailUrl(array $row, board $board): string {
		// No file attached to this OP — use generic placeholder
		if (empty($row['file_stored_name']) || empty($row['file_extension'])) {
			return $board->getConfigValue('STATIC_URL') . 'image/nothumb.gif';
		}

		// Build the attachment array expected by the shared helper
		$attachment = [
			'storedFileName' => $row['file_stored_name'],
			'fileExtension' => $row['file_extension'],
			'fileWidth' => (int) $row['file_width'],
			'fileHeight' => (int) $row['file_height'],
			'mimeType' => $row['file_mime_type'] ?? null,
			'isHidden' => (bool) ($row['file_is_hidden'] ?? false),
			'fileId' => (int) ($row['file_id'] ?? 0),
			'boardUID' => (int) $row['boardUID'],
		];

		return resolveThumbnailDisplayUrl($attachment, $board);
	}
}
