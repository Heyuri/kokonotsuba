<?php

class deletedPostsRepository {
	private array $allowedOrderFields;

	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $deletedPostsTable,
		private readonly string $postTable
	) {
		$this->allowedOrderFields = [
			'id',
			'post_uid',
			'deleted_at',
			'restored_at'
		];
	}

	public function purgeDeletedPostsByAccountId(int $accountId): void {
		// query to delete rows that have been deleted by a specified account id
		$query = "DELETE FROM {$this->deletedPostsTable} WHERE deleted_by = :account_id";

		// parameters
		$params = [
			':account_id' => $accountId
		];

		// execute the query
		$this->databaseConnection->execute($query, $params);
	}

	public function getAllPostUidsFromAccountId(int $accountId): array|false {
		// query to get all post uids from posts deleted by the specified account id
		$query = "DELETE FROM {$this->deletedPostsTable} WHERE deleted_by = :account_id";

		// parameters
		$params = [
			':account_id' => $accountId
		];
		
		// fetch the data
		$postUids = $this->databaseConnection->fetchAllAsIndexArray($query, $params);

		// return the data
		return $postUids;
	}

	public function restorePostData(int $deletedPostId, int $accountId): void {
		// query to mark posts as restored
		$query = "UPDATE {$this->deletedPostsTable} 
			SET restored_at = CURRENT_TIMESTAMP
				restored_by = :account_id
			WHERE id = :deleted_post_id";

		// parameters
		$params = [
			':account_id' => $accountId,
			':deleted_post_id' => $deletedPostId
		];

		// execute the query
		$this->databaseConnection->execute($query, $params); 
	}

	public function purgeDeletedPostsById(int $deletedPostId): void {
		// query to purge the post
		// there's a foreign key with ON DELETE CASCADE on the deleted posts table so we only need to delete the post from the post table and it'll handle the associated row on its own
		$query = "DELETE FROM posts
			WHERE post_uid = (
				SELECT post_uid
				FROM deleted_posts
				WHERE id = :deleted_post_id
			);
		";

		// params
		$params = [
			':deleted_post_id' => $deletedPostId
		];

		// execute the query
		$this->databaseConnection->execute($query, $params); 
	}

	public function getPostByDeletedPostId(int $deletedPostId): array|false {
		// query to get the post data by deleted post id
		$query = "SELECT * FROM {$this->postTable} WHERE post_uid = 
			(SELECT post_uid FROM {$this->deletedPostsTable} WHERE id = :deleted_post_id)";

		// parameters
		$params = [
			':deleted_post_id' => $deletedPostId
		];

		// fetch the data as a single row
		$postData = $this->databaseConnection->fetchOne($query, $params);
	
		// return it
		return $postData;
	}

	public function getDeletedPosts(int $amount, int $offset, string $orderBy = 'id', string $direction = 'DESC'): array|false {
		// Get the query for deleted posts
		$query = $this->buildDeletedPostsQuery($amount, $offset, $orderBy, $direction);

		// fetch all the data as an array
		$deletedPosts = $this->databaseConnection->fetchAllAsArray($query);

		// return results
		return $deletedPosts;
	}

	public function getDeletedPostsByAccountId(int $accountId, int $amount, int $offset, string $orderBy = 'id', string $direction = 'DESC'): array|false {
		// Get the query for deleted posts by account ID
		$query = $this->buildDeletedPostsQuery($amount, $offset, $orderBy, $direction, $accountId);
	
		// params
		$params = $accountId ? [':account_id' => $accountId] : [];
	
		// fetch all the data as an array
		$deletedPosts = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		// return results
		return $deletedPosts;
	}

	private function buildDeletedPostsQuery(int $amount, int $offset, string $orderBy = 'id', string $direction = 'DESC', ?int $accountId = null): string {
		// Validate orderBy
		if (!in_array($orderBy, $this->allowedOrderFields, true)) {
			$orderBy = 'id';
		}

		// Validate direction
		$direction = strtoupper($direction);
		if (!in_array($direction, ['ASC', 'DESC'], true)) {
			$direction = 'DESC';
		}

		// Base query
		$query = "SELECT * FROM {$this->deletedPostsTable}";

		// Add condition if accountId is provided
		if ($accountId !== null) {
			$query .= " WHERE deleted_by = :account_id";
		}

		// Add ordering and pagination
		$query .= " ORDER BY {$orderBy} {$direction} LIMIT {$amount} OFFSET {$offset}";

		return $query;
	}

	public function getTotalAmountOfDeletedPosts(): int {
		// query to get the total amount of deleted posts
		$query = "SELECT COUNT(*) FROM {$this->deletedPostsTable} WHERE 1";

		// fetch the count value
		$totalAmount = $this->databaseConnection->fetchColumn($query);

		// return it
		return $totalAmount;
	}

	public function getTotalAmountOfDeletedPostsByAccountId(int $accountId): int {
		// query to get the total amount of deleted posts
		$query = "SELECT COUNT(*) FROM {$this->deletedPostsTable} WHERE deleted_by = :account_id";

		// parameters
		$params = [
			':account_id' => $accountId
		];

		// fetch the count value
		$totalAmount = $this->databaseConnection->fetchColumn($query, $params);

		// return it
		return $totalAmount;
	}

	public function deletedPostExistsByAccountId(int $deletedPostId, int $accountId): bool {
		// query to check if the deleted post row exists
		$query = "
			SELECT 1 FROM {$this->deletedPostsTable}
			WHERE id = :deleted_post_id AND deleted_by = :account_id
			LIMIT 1
		";

		// parameters
		$params = [
			':deleted_post_id' => $deletedPostId,
			':account_id' => $accountId
		];

		// fetch the result as a single value
		$result = $this->databaseConnection->fetchOne($query, $params);

		// if its not false return true
		return $result !== false;
	}

	public function purgeDeletedPostsFromList(array $deletedPostsList): void {
		// '?' placeholders for the IN clause
		$inClause = pdoPlaceholdersForIn($deletedPostsList);

		// query to purge the deleted posts
		$query = "
			DELETE FROM posts
				WHERE post_uid IN (
				SELECT post_uid
				FROM {$this->deletedPostsTable}
				WHERE id IN $inClause
			);
		";

		// parameters
		// just the deletedPostsList so the values are properly assigned
		$parameters = $deletedPostsList;

		// execute the query to delete the posts
		$this->databaseConnection->execute($query, $parameters);
	}

	public function getIDsFromListByAccountId(array $deletedPostsList, int $accountId): array {
		// '?' placeholders for the IN clause
		$inClause = pdoPlaceholdersForIn($deletedPostsList);

		// query to return IDs that were deleted by the account
		$query = "SELECT id FROM {$this->deletedPostsTable} WHERE IN $inClause AND deleted_by = ?";

		// parameters
		$parameters = array_merge($deletedPostsList, [$accountId]);

		// fetch the id list from database
		$deletedPostIds = array_merge(...$this->databaseConnection->fetchAllAsIndexArray($query, $parameters));

		// return the results
		return $deletedPostIds;
	}

}