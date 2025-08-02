<?php

class quoteLinkRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $quoteLinkTable,
		private readonly string $postTable,
		private readonly string $threadTable
	) {}

	private function getQuoteLinkQuery(): string {
		$query = "
			SELECT 
				q.quotelink_id,
				q.board_uid,
				q.target_post_uid,
				q.host_post_uid,

				tp.no AS target_no,
				tt.post_op_number AS target_post_op_number,

				hp.no AS host_no,
				ht.post_op_number AS host_post_op_number
			FROM {$this->quoteLinkTable} q
			JOIN {$this->postTable} tp ON q.target_post_uid = tp.post_uid
			JOIN {$this->threadTable} tt ON tp.thread_uid = tt.thread_uid

			JOIN {$this->postTable} hp ON q.host_post_uid = hp.post_uid
			JOIN {$this->threadTable} ht ON hp.thread_uid = ht.thread_uid
		";

		return $query;
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
				],
				'host_post' => [
					'post_uid' => (int)$row['host_post_uid'],
					'no' => (int)$row['host_no'],
					'post_op_number' => (int)$row['host_post_op_number'],
				]
			];
		}

		return $results;
	}

	public function getQuoteLinksByPostUids(array $postUids): array {
		if (empty($postUids)) {
			return [];
		}

		$inClause = pdoPlaceholdersForIn($postUids);
		$allParams = array_merge($postUids, $postUids);

		// get the query
		$query = $this->getQuoteLinkQuery();

		// add the IN clause for getting the posts by uids
		$query .= "WHERE q.target_post_uid IN $inClause OR q.host_post_uid IN $inClause";

		// fetch the data from the database
		$rows = $this->databaseConnection->fetchAllAsArray($query, $allParams);

		// get the results
		$results = $this->prepareResults($rows);

		return $results;
	}

	public function getQuoteLinksByBoardUid(int $boardUid): array {
		// get the query
		$query = $this->getQuoteLinkQuery();

		// add the IN clause for getting the posts by uids
		$query .= "WHERE q.board_uid = :board_uid";

		// board param
		$params = [
			':board_uid' => $boardUid
		];

		// fetch the data from the database
		$rows = $this->databaseConnection->fetchAllAsArray($query, $params);

		// get the results
		$results = $this->prepareResults($rows);

		return $results;
	}

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

		$query = "INSERT INTO {$this->quoteLinkTable} (board_uid, host_post_uid, target_post_uid) VALUES " . implode(', ', $placeholders);
		return $this->databaseConnection->execute($query, $params);
	}

	public function updateQuoteLinkBoardUids(array $quoteLinkIds, int $boardUid): void {
		if (empty($quoteLinkIds)) {
			return;
		}

		$idList = implode(',', array_map('intval', $quoteLinkIds));
		$query = "UPDATE {$this->quoteLinkTable} SET board_uid = {$boardUid} WHERE quotelink_id IN ($idList)";
		$this->databaseConnection->execute($query);
	}

	public function getQuoteLinksFromHostPostUids(array $postUids): array {
		$inClause = implode(',', array_map('intval', $postUids));
		$query = "SELECT * FROM {$this->quoteLinkTable} WHERE host_post_uid IN ($inClause)";
		return $this->databaseConnection->fetchAllAsClass($query, [], 'quoteLink');
	}
}
