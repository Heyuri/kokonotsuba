<?php

class deletedPostsRepository {
	private array $allowedOrderFields;

	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $deletedPostsTable,
		private readonly string $postTable,
		private readonly string $accountTable,
		private readonly string $fileTable
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
		$query = "SELECT post_uid FROM {$this->deletedPostsTable} WHERE deleted_by = :account_id";

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
		$query = $this->getBaseRestoreQuery();

		// append where clause
		$query .= " WHERE id = :deleted_post_id";

		// parameters
		$params = [
			':account_id' => $accountId,
			':deleted_post_id' => $deletedPostId
		];

		// execute the query
		$this->databaseConnection->execute($query, $params); 
	}

	public function restorePostsByThreadUid(string $threadUid, int $accountId): void {
		// query to mark posts as restored by thread uid
		$query = $this->getBaseRestoreQuery();

		// appened where clause
		// only restore posts that hold the specified `thread_uid`
		$query .= " WHERE post_uid 
				IN( SELECT post_uid FROM {$this->postTable} 
					WHERE thread_uid = :thread_uid
		)";

		// parameters
		$params = [
			':thread_uid' => $threadUid,
			':account_id' => $accountId
		];

		// execute restore query
		$this->databaseConnection->execute($query, $params);
	}

	private function getBaseRestoreQuery(): string {
		// query to restore posts
		$query = "UPDATE {$this->deletedPostsTable} 
				SET restored_at = CURRENT_TIMESTAMP,
					restored_by = :account_id,
					file_only = 0";

		// return query
		return $query;
	}

	public function purgeDeletedPostById(int $deletedPostId): void {
		// query to purge the post
		// there's a foreign key with ON DELETE CASCADE on the deleted posts table so we only need to delete the post from the post table and it'll handle the associated row on its own
		$query = "DELETE FROM {$this->postTable}
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
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable);
		
		// append WHERE clause
		$query .= " WHERE p.post_uid = 
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

	public function getPostsByIdList(array $postUids): array|false {
		// base query to get the posts data by deleted post id
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable);
		
		// generate IN clause for post uids
		$inClause = pdoPlaceholdersForIn($postUids);

		// append WHERE clause
		$query .= " WHERE p.post_uid IN (
					SELECT post_uid FROM {$this->deletedPostsTable} WHERE id IN $inClause
				)";

		// parameters
		$params = $postUids;

		// fetch the posts as an array
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);

		// return posts
		return $posts;
	}

	public function getPagedEntries(int $amount, int $offset, string $orderBy = 'id', string $direction = 'DESC', bool $restoredOnly = false, ?int $accountId = null): array|false {
		// Hide restored posts
		if(!$restoredOnly) {
			// append where clause to exclude restored posts
			$whereClause = " WHERE base.open_flag = 1 AND base.by_proxy = 0";
		} else {
			// otherwise filter for closed flags
			$whereClause = 'WHERE base.open_flag = 0 AND base.by_proxy = 0';
		}

		// init params array 
		$params = [];

		// append accound if if we're filtering for those
		if($accountId) {
			// append to WHERE clause
			$whereClause .= ' AND dp_meta.deleted_by = :account_id';

			// add :account_id placeholder to params
			$params[':account_id'] = $accountId;
		}

		// Get the query for entries
		$query = $this->buildDeletedPostsQuery($amount, $offset, $orderBy, $direction, $whereClause);

		// fetch all the data as an array
		$entries = $this->databaseConnection->fetchAllAsArray($query, $params);

		// return results
		return $entries;
	}

	public function getDeletedPostRowById(int $deletedPostId): array|false {
		// Get the query for deleted posts
		$query = $this->buildDeletedPostByIdQuery();

		// parameters
		$params = [
			':id' => $deletedPostId
		];

		// fetch the single row
		$deletedPost = $this->databaseConnection->fetchOne($query, $params);

		// return data
		return $deletedPost;
	}

	private function getBaseDeletedPostsQuery(): string {
		$query = "
			SELECT
				base.*,
				-- Optional: expose deleted/restored timestamps & actor IDs
				dp_meta.deleted_at,
				dp_meta.deleted_by,
				dp_meta.restored_at,
				dp_meta.restored_by,
				dp_meta.id AS deleted_post_id,
				dp_meta.note,
				dp_meta.by_proxy,

				-- Usernames
				da.username AS deleted_by_username,
				ra.username AS restored_by_username
			FROM (
				" . getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable) . "
			) base
			-- Pull only the extra dp fields we didn't already include in base
			LEFT JOIN (
				SELECT dp1.post_uid, dp1.deleted_at, dp1.deleted_by, dp1.restored_at, dp1.restored_by, dp1.id, dp1.note, dp1.by_proxy
				FROM {$this->deletedPostsTable} dp1
				INNER JOIN (
					SELECT post_uid, MAX(deleted_at) AS max_deleted_at
					FROM {$this->deletedPostsTable}
					GROUP BY post_uid
				) dp2 ON dp1.post_uid = dp2.post_uid AND dp1.deleted_at = dp2.max_deleted_at
			) dp_meta ON base.post_uid = dp_meta.post_uid
			LEFT JOIN {$this->accountTable} da ON dp_meta.deleted_by = da.id
			LEFT JOIN {$this->accountTable} ra ON dp_meta.restored_by = ra.id
		";
		return $query;
	}

	private function buildDeletedPostsQuery(
		int $amount, 
		int $offset, 
		string $orderBy = 'id', 
		string $direction = 'DESC',
		string $whereClause = '',
	): string {
		// Validate orderBy
		if (!in_array($orderBy, $this->allowedOrderFields, true)) {
			$orderBy = 'id';
		}

		// Validate direction
		$direction = strtoupper($direction);
		if (!in_array($direction, ['ASC', 'DESC'], true)) {
			$direction = 'DESC';
		}

		// Start with shared SELECT + JOIN block
		$query = $this->getBaseDeletedPostsQuery();

		// Append the WHERE Clause
		$query .= $whereClause;

		// Add ordering and pagination
		$query .= " ORDER BY dp_meta.{$orderBy} {$direction} LIMIT {$amount} OFFSET {$offset}";

		return $query;
	}

	private function buildDeletedPostByIdQuery(): string {
		$query = $this->getBaseDeletedPostsQuery();
		$query .= " WHERE dp_meta.id = :id LIMIT 1";
		
		return $query;
	}

	public function getTotalAmountOfDeletedPosts(): int {
		// query to get the total amount of deleted posts
		$query = "SELECT COUNT(*) FROM {$this->deletedPostsTable} WHERE open_flag = 1 AND by_proxy = 0";

		// fetch the count value
		$totalAmount = $this->databaseConnection->fetchColumn($query);

		// return it
		return $totalAmount;
	}

	public function getTotalAmountOfDeletedPostsByAccountId(int $accountId): int {
		// query to get the total amount of deleted posts
		$query = "SELECT COUNT(*) FROM {$this->deletedPostsTable} WHERE deleted_by = :account_id AND open_flag = 1 AND by_proxy = 0";

		// parameters
		$params = [
			':account_id' => $accountId
		];

		// fetch the count value
		$totalAmount = $this->databaseConnection->fetchColumn($query, $params);

		// return it
		return $totalAmount;
	}

	public function getTotalAmount(?int $accountId = null, bool $restoredOnly = false): int {
		// query to get the total amount
		$query = "SELECT COUNT(*) FROM {$this->deletedPostsTable}";

		// init params
		$params = [];

		// only count deleted posts
		if(!$restoredOnly) {
			// search for rows where open_flag is 1 (open, not restored)
			$whereClause = " WHERE open_flag = 1 AND by_proxy = 0";
		}
		// only count restored posts
		else {
			// search for rows where open_flag is 0 (restored)
			$whereClause = " WHERE open_flag = 0 AND by_proxy = 0";
		}

		// if an account id is selected then append the account id to the search query for who deleted it
		if($accountId) {
			// append to where clause
			$whereClause .= " AND deleted_by = :account_id";

			// add account id paramter
			$params[':account_id'] = $accountId;
		}

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
			DELETE FROM {$this->postTable}
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

	public function insertDeletedPostEntry(int $postUid, ?int $deletedBy , bool $fileOnly, bool $byProxy): void {
		// query to insert a deleted post entry
		$query = "INSERT INTO {$this->deletedPostsTable} 
			(post_uid, deleted_by, file_only, by_proxy) 
			VALUES (:post_uid, :deleted_by, :file_only, :by_proxy)
			ON DUPLICATE KEY UPDATE
				file_only = LEAST(file_only, VALUES(file_only)),
				deleted_by = VALUES(deleted_by),
				deleted_at = VALUES(deleted_at)
		";

	
		// bind parameters
		$parameters = [
			':post_uid' => $postUid,
			':deleted_by' => $deletedBy,
			':file_only' => (int) $fileOnly,
			':by_proxy' => (int) $byProxy
		];

		// execute query and insert entry
		$this->databaseConnection->execute($query, $parameters);
	}

	public function updateDeletedPostNoteById(int $deletedPostId, string $note): void {
		// query to UPDATE the note column for the specified post
		$query = "UPDATE {$this->deletedPostsTable} SET note = :note WHERE id = :deleted_post_id";

		// parameters
		$parameters = [
			':note' => $note,
			':deleted_post_id' => $deletedPostId
		];

		// execute query and update entry
		$this->databaseConnection->execute($query, $parameters);
	}

	public function getBoardUidByDeletedPostId(int $deletedPostId): false|int {
	    // query to get boardUID from post table via post_uid in deletedPosts table
	    $query = "
	        SELECT p.boardUID
	        FROM {$this->deletedPostsTable} dp
	        INNER JOIN {$this->postTable} p ON p.post_uid = dp.post_uid
	        WHERE dp.id = :deleted_post_id
	    ";

	    // parameters
	    $parameters = [
	        ':deleted_post_id' => $deletedPostId
	    ];

	    // query database
	    $boardUid = $this->databaseConnection->fetchValue($query, $parameters);

	    // return result
	    return $boardUid;
	}

	public function getDeletedPostRowByPostUid(int $postUid): false|array {
		// query to fetch the deleted post by post uid
		$query = $this->getBaseDeletedPostsQuery();

		// select the post by post uid
		$query .= " WHERE base.post_uid = :post_uid";

		// query parameteres
		$params = [
			':post_uid' => $postUid
		];

		// fetch the row
		$deletedPost = $this->databaseConnection->fetchOne($query, $params);

		// return result
		return $deletedPost;
	}

	public function getExpiredEntryIDs(int $timeLimit): false|array {
		// query to get entries older than the time limit (in hours) 
		$query = "SELECT id
    		FROM {$this->deletedPostsTable}
    		WHERE deleted_at < NOW() - INTERVAL {$timeLimit} HOUR
			AND COALESCE(open_flag, 0) = 1";

		// fetch the results as array
		$entries = $this->databaseConnection->fetchAllAsIndexArray($query);

		// unpack
		$entries = array_merge(...$entries);

		// return the entries
		return $entries;
	}

	public function removeRowById(int $id): void {
		// query to remove a row by its ID
		$query = "DELETE FROM {$this->deletedPostsTable} WHERE id = :id";
		
		// parameter
		$params = [
			':id' => $id
		];

		// execute query
		$this->databaseConnection->execute($query, $params);
	}
}