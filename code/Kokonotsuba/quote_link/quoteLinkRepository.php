<?php

namespace Kokonotsuba\quote_link;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;
use function Kokonotsuba\libraries\objectivePositionSubquery;

/** Repository for quote-link records that track post-to-post reply references. */
class quoteLinkRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $quoteLinkTable,
		private readonly string $postTable,
		private readonly string $threadTable,
		private readonly string $deletedPostsTable
	) {
		parent::__construct($databaseConnection, $quoteLinkTable);
		self::validateTableNames($postTable, $threadTable, $deletedPostsTable);
	}

	private function indexQuoteLinksByHostPostUid(array $quoteLinks): array {
		$indexed = [];
		foreach ($quoteLinks as $entry) {
			if (isset($entry['host_post']['post_uid'])) {
				$hostPostUid = $entry['host_post']['post_uid'];
				$indexed[$hostPostUid][] = $entry;
			}
		}
		return $indexed;
	}

	private function prepareResults(array $rows): array {
		$results = [];
		foreach ($rows as $row) {
			$results[] = [
				'quote_link' => [
					'quotelink_id' => (int)$row['quotelink_id'],
					'board_uid' => (int)$row['board_uid'],
					'host_post_uid' => (int)$row['host_post_uid'],
					'target_post_uid' => (int)$row['target_post_uid'],
				],
				'target_post' => [
					'post_uid' => (int)$row['target_post_uid'],
					'no' => (int)$row['target_no'],
					'post_op_number' => (int)$row['target_post_op_number'],
					'post_position' => (int)$row['target_post_position'],
					'board_uid' => (int)$row['target_board_uid'],
				],
				// only the uid: the entry is filed under it, and nothing reads any other
				// host-side field, so the query no longer joins the host post or its thread
				'host_post' => [
					'post_uid' => (int)$row['host_post_uid'],
				]
			];
		}

		// index the results by host_post_uid
		$indexedQuoteLinks = $this->indexQuoteLinksByHostPostUid($results);

		return $indexedQuoteLinks;
	}

	private function getQuoteLinkQuery(): string {
		// Objective position of the quoted (target) post within its thread — this is what
		// the quote-link URL's page number is derived from. Using the stored post_position
		// column here would point links at the wrong page once replies have been deleted.
		$targetObjectivePosition = objectivePositionSubquery($this->postTable, $this->deletedPostsTable, 'tp', false);

		$query = "
			SELECT
				q.quotelink_id,
				q.board_uid,
				q.target_post_uid,
				q.host_post_uid,

				tp.no AS target_no,
				tt.post_op_number AS target_post_op_number,

				tp.boardUID AS target_board_uid,
				{$targetObjectivePosition} AS target_post_position,

				hdp.open_flag AS host_open_flag,
				tdp.open_flag AS target_open_flag,
				hdp.file_only AS host_file_only,
				tdp.file_only AS target_file_only

			FROM {$this->table} q
			JOIN {$this->postTable} tp ON q.target_post_uid = tp.post_uid
			JOIN {$this->threadTable} tt ON tp.thread_uid = tt.thread_uid

			-- No join onto the host post or its thread: prepareResults() keeps only
			-- host_post_uid, which the quote-link row already carries. The deletion state below
			-- hangs off q.host_post_uid directly.
			LEFT JOIN {$this->deletedPostsTable} hdp ON q.host_post_uid = hdp.post_uid AND hdp.open_flag = 1
			LEFT JOIN {$this->deletedPostsTable} tdp ON q.target_post_uid = tdp.post_uid AND tdp.open_flag = 1
		";

		return $query;
	}

	/**
	 * Fetch the quote-links *originating from* the given post UIDs, with target post-number metadata.
	 *
	 * Links pointing *at* these posts are not returned. Results are indexed by host_post_uid and
	 * every caller looks them up with the uid of a post it is about to render, so an entry whose
	 * host lies outside $postUids can never be read back out.
	 *
	 * @param array $postUids                     Array of post UIDs to look up.
	 * @param bool  $includeDeletedPostQuotelinks Whether to include links where a post is deleted.
	 * @return array Quote-link results indexed by host_post_uid.
	 */
	public function getQuoteLinksByPostUids(array $postUids, bool $includeDeletedPostQuotelinks = false): array {
		if (empty($postUids)) {
			return [];
		}

		$inClause = pdoPlaceholdersForIn($postUids);

		// get the query
		$query = $this->getQuoteLinkQuery();

		// Host side only. This used to also match "OR q.target_post_uid IN (...)", which on a board
		// whose rendered posts are popular quote targets pulled back every post that had ever
		// quoted them - thousands of rows, each costing a correlated position subquery and an
		// array in prepareResults(), all of them filed under a host uid nobody looks up.
		$query .= "WHERE q.host_post_uid IN $inClause";

		// if we want to exclude quote links from deleted posts
		if(!$includeDeletedPostQuotelinks) {
			$query .= " AND (
				COALESCE(hdp.open_flag, tdp.open_flag) IS NULL
				OR (
					COALESCE(hdp.file_only, 0) = 1
					OR COALESCE(tdp.file_only, 0) = 1
				)
			)";
		}

		$rows = $this->queryAll($query, array_values($postUids));
		return $this->prepareResults($rows);
	}

	/**
	 * Fetch all quote-links belonging to the given board UID.
	 *
	 * @param int $boardUid Board UID to filter by.
	 * @return array Quote-link results indexed by host_post_uid.
	 */
	public function getQuoteLinksByBoardUid(int $boardUid): array {
		$query = $this->getQuoteLinkQuery();
		$query .= "WHERE q.board_uid = :board_uid";
		$rows = $this->queryAll($query, [':board_uid' => $boardUid]);
		return $this->prepareResults($rows);
	}

	/**
	 * Batch-insert quote-link records and return the number of rows affected.
	 * Each item must have 'board_uid', 'host_post_uid', and 'target_post_uid' keys.
	 *
	 * @param array $quoteLinks Array of link data arrays.
	 * @return int Number of rows inserted.
	 */
	public function insertQuoteLinks(array $quoteLinks): int {
		if (empty($quoteLinks)) {
			return 0;
		}

		$placeholders = [];
		$params = [];

		foreach ($quoteLinks as $link) {
			if (
				!isset($link['host_post_uid'], $link['target_post_uid'], $link['board_uid']) ||
				!is_numeric($link['host_post_uid']) ||
				!is_numeric($link['target_post_uid']) ||
				!is_numeric($link['board_uid'])
			) {
				continue;
			}

			$placeholders[] = "(?, ?, ?)";
			$params[] = (int) $link['board_uid'];
			$params[] = (int) $link['host_post_uid'];
			$params[] = (int) $link['target_post_uid'];
		}

		if (empty($placeholders)) {
			return 0;
		}

		$query = "INSERT INTO {$this->table} (board_uid, host_post_uid, target_post_uid) VALUES " . implode(', ', $placeholders);
		return $this->query($query, $params);
	}

	/**
	 * Move a set of quote-links to a different board.
	 *
	 * @param array $quoteLinkIds Array of quotelink_id values to update.
	 * @param int   $boardUid     Destination board UID.
	 * @return void
	 */
	public function updateQuoteLinkBoardUids(array $quoteLinkIds, int $boardUid): void {
		if (empty($quoteLinkIds)) {
			return;
		}

		$this->updateWhereIn(['board_uid' => $boardUid], 'quotelink_id', $quoteLinkIds);
	}

	/**
	 * Fetch all quote-links where the host post is one of the given post UIDs, as hydrated quoteLink objects.
	 *
	 * @param array $postUids Array of host post UIDs.
	 * @return quoteLink[] Array of hydrated quoteLink objects.
	 */
	public function getQuoteLinksFromHostPostUids(array $postUids): array {
		return $this->findAllWhereIn('host_post_uid', $postUids, '\Kokonotsuba\quote_link\quoteLink');
	}
}
