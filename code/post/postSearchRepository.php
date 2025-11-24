<?php

class postSearchRepository {
	public function __construct(
        private DatabaseConnection $databaseConnection,
        private readonly string $postTable, 
        private readonly string $threadTable,
        private readonly string $deletedPostsTable,
        private readonly string $fileTable,
    ) {}

	public function fetchPostsByLike(string $field, string $boardUID, string $encoded, int $limit, int $offset): false|array {
		$params = [
			':phrase' => '[[:<:]]' . $encoded . '[[:>:]]',
			':board_uid' => $boardUID
		];

		// get the base post query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// append WHERE
		$query .= " WHERE p.$field REGEXP :phrase AND p.boardUID = :board_uid";

		// exclude deleted posts
		$query = excludeDeletedPostsCondition($query);

		// append order, limit and offset
		$query .= "
			ORDER BY p.no DESC
			LIMIT $limit OFFSET $offset
		";

		// fetch posts from db
		$postsResults = $this->databaseConnection->fetchAllAsArray($query, $params);;

		// merge attachment rows
		$mergedPosts = mergeMultiplePostRows($postsResults);

		// return merged posts
		return $mergedPosts;
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

	public function fetchPostsByFullText(string $field, string $boardUID, string $searchString, int $limit, int $offset): false|array {
		$params = [
			':search' => $searchString,
			':board_uid' => $boardUID
		];

		// get base post query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// append base WHERE condition
		$query .= "
			WHERE MATCH(p.$field) AGAINST (:search IN BOOLEAN MODE) 
			AND p.boardUID = :board_uid";

		// exclude deleted posts
		$query = excludeDeletedPostsCondition($query);

		// append order / limit / offset
		$query .= "
			ORDER BY p.no DESC
			LIMIT $limit OFFSET $offset
		";

		// fetch posts from db
		$postsResults = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment rows
		$mergedPosts = mergeMultiplePostRows($postsResults);

		// return merged posts
		return $mergedPosts;
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
