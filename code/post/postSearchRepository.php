<?php

class postSearchRepository {
	public function __construct(
        private DatabaseConnection $databaseConnection,
        private readonly string $postTable, 
        private readonly string $threadTable
    ) {}

	public function fetchPostsByLike(string $field, string $boardUID, string $encoded, int $limit, int $offset): array {
		$params = [
			':phrase' => '[[:<:]]' . $encoded . '[[:>:]]',
			':board_uid' => $boardUID
		];

		$query = "
			SELECT p.*, t.*
			FROM {$this->postTable} p
			LEFT JOIN {$this->threadTable} t ON p.thread_uid = t.thread_uid
			WHERE p.$field REGEXP :phrase AND p.boardUID = :board_uid
			ORDER BY p.no DESC
			LIMIT $limit OFFSET $offset
		";

		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function countPostsByLike(string $field, string $boardUID, string $encoded): int {
		$params = [
			':phrase' => '[[:<:]]' . $encoded . '[[:>:]]',
			':board_uid' => $boardUID
		];

		$countQuery = "
			SELECT COUNT(*) AS total_posts
			FROM {$this->postTable}
			WHERE $field REGEXP :phrase AND boardUID = :board_uid
		";

		return $this->databaseConnection->fetchOne($countQuery, $params)['total_posts'] ?? 0;
	}

	public function fetchPostsByFullText(string $field, string $boardUID, string $searchString, int $limit, int $offset): array {
		$params = [
			':search' => $searchString,
			':board_uid' => $boardUID
		];

		$query = "
			SELECT p.*, t.*
			FROM {$this->postTable} p
			LEFT JOIN {$this->threadTable} t ON p.thread_uid = t.thread_uid
			WHERE MATCH(p.$field) AGAINST (:search IN BOOLEAN MODE)
			AND p.boardUID = :board_uid
			ORDER BY p.no DESC
			LIMIT $limit OFFSET $offset
		";

		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function countPostsByFullText(string $field, string $boardUID, string $searchString): int {
		$params = [
			':search' => $searchString,
			':board_uid' => $boardUID
		];

		$countQuery = "
			SELECT COUNT(*) AS total_posts
			FROM {$this->postTable}
			WHERE MATCH($field) AGAINST (:search IN BOOLEAN MODE)
			AND boardUID = :board_uid
		";

		return $this->databaseConnection->fetchOne($countQuery, $params)['total_posts'] ?? 0;
	}
}
