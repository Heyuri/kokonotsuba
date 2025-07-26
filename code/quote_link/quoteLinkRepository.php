<?php

class quoteLinkRepository {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $quoteLinkTable,
		private readonly string $postTable,
		private readonly string $threadTable
	) {}

	public function getQuoteLinksByTargetPostUids(array $postUids): array {
		$inClause = implode(',', array_map('intval', $postUids));
		$query = "SELECT * FROM {$this->quoteLinkTable} WHERE target_post_uid IN ($inClause)";
		return $this->databaseConnection->fetchAllAsClass($query, [], 'quoteLink');
	}

	public function getPostsByPostUids(array $postUids): array {
		$postInClause = implode(',', array_map('intval', $postUids));
		$query = "SELECT * FROM {$this->postTable} WHERE post_uid IN ($postInClause)";
		return $this->databaseConnection->fetchAllAsArray($query);
	}

	public function getQuoteLinksByBoardUid(int $boardUid): array {
		$query = "SELECT * FROM {$this->quoteLinkTable} WHERE board_uid = :board_uid";
		return $this->databaseConnection->fetchAllAsClass($query, [':board_uid' => $boardUid], 'quoteLink');
	}

	public function getThreadsByUids(array $threadUids): array {
		$placeholders = [];
		$params = [];

		foreach ($threadUids as $i => $uid) {
			$placeholder = ":uid{$i}";
			$placeholders[] = $placeholder;
			$params[$placeholder] = $uid;
		}

		$query = "SELECT * FROM {$this->threadTable} WHERE thread_uid IN (" . implode(',', $placeholders) . ")";
		return $this->databaseConnection->fetchAllAsArray($query, $params);
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
