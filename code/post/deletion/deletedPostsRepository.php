<?php

class deletedPostsRepository {
	private array $allowedOrderFields;

	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $deletedPostsTable,
		private readonly string $postTable,
		private readonly string $accountTable,
		private readonly string $fileTable,
		private readonly string $threadTable
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
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);
		
		// append WHERE clause
		$query .= " WHERE p.post_uid = 
					(SELECT post_uid FROM {$this->deletedPostsTable} WHERE id = :deleted_post_id)";

		// parameters
		$params = [
			':deleted_post_id' => $deletedPostId
		];

		// fetch the data as a single row
		$postData = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		// merge attachment row
		$postData = mergeDeletedPostRows($postData);

		// return it
		return $postData[0] ?? false;
	}

	public function getPostsByIdList(array $postUids): array|false {
		// base query to get the posts data by deleted post id
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);
		
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

		// merge attachment rows
		$posts = mergeDeletedPostRows($posts);

		// return posts
		return $posts;
	}

	public function getPagedEntries(int $amount, int $offset, string $orderBy = 'id', string $direction = 'DESC', bool $restoredOnly = false, ?int $accountId = null): array|false {
		// Hide restored posts
		if(!$restoredOnly) {
			// append where clause to exclude restored posts
			$whereClause = " WHERE dp.open_flag = 1 AND dp.by_proxy = 0";
		} else {
			// otherwise filter for closed flags
			$whereClause = ' WHERE dp.open_flag = 0 AND dp.by_proxy = 0';
		}

		// init params array 
		$params = [];

		// append accound if if we're filtering for those
		if($accountId) {
			// append to WHERE clause
			$whereClause .= ' AND dp.deleted_by = :account_id';

			// add :account_id placeholder to params
			$params[':account_id'] = $accountId;
		}

		// Get the query for entries
		$query = $this->buildDeletedPostsQuery($amount, $offset, $orderBy, $direction, $whereClause);

		// fetch all the data as an array
		$entries = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment
		$entries = mergeDeletedPostRows($entries);

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
		$deletedPost = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment rows
		$deletedPost = mergeDeletedPostRows($deletedPost);

		// return data
		return $deletedPost[0] ?? false;
	}

	private function getBaseDeletedPostsQuery(): string {
		return "
			SELECT
				dp.open_flag,
				dp.id                AS deleted_post_id,
				dp.post_uid          AS post_uid,
				dp.deleted_at        AS deleted_at,
				dp.deleted_by        AS deleted_by,
				dp.restored_at,
				dp.restored_by,
				dp.by_proxy,
				dp.file_only         AS file_only_deleted,
				dp.file_id           AS file_id,
				dp.note,

				-- Post data (may be null if the post itself is gone)
				p.*,

				-- Attachment belonging specifically to THIS dp row
				f.id                 AS attachment_id,
				f.file_name          AS attachment_file_name,
				f.stored_filename    AS attachment_stored_filename,
				f.file_ext           AS attachment_file_ext,
				f.file_md5           AS attachment_file_md5,
				f.file_size          AS attachment_file_size,
				f.file_width         AS attachment_file_width,
				f.file_height        AS attachment_file_height,
				f.thumb_file_width   AS attachment_thumb_width,
				f.thumb_file_height  AS attachment_thumb_height,
				f.mime_type          AS attachment_mime_type,
				f.is_hidden          AS attachment_is_hidden,
				f.is_animated        AS attachment_is_animated,
				f.is_deleted         AS attachment_is_deleted,
				f.timestamp_added    AS attachment_timestamp_added,

				da.username AS deleted_by_username,
				ra.username AS restored_by_username

			FROM {$this->deletedPostsTable} dp

			-- Post content
			LEFT JOIN {$this->postTable} p
				ON p.post_uid = dp.post_uid

			-- Attachments belonging to THIS deletion event
			-- (file_id will be null for post-level deletes)
			LEFT JOIN {$this->fileTable} f
				ON f.post_uid = dp.post_uid

			LEFT JOIN {$this->accountTable} da ON dp.deleted_by = da.id
			LEFT JOIN {$this->accountTable} ra ON dp.restored_by = ra.id
		";
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
		$query .= " ORDER BY dp.{$orderBy} {$direction} LIMIT {$amount} OFFSET {$offset}";

		return $query;
	}

	private function buildDeletedPostByIdQuery(): string {
		$query = $this->getBaseDeletedPostsQuery();
		$query .= " WHERE dp.id = :id LIMIT 1";
		
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

		// append where clause to query
		$query .= $whereClause;

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

	public function insertDeletedPostEntry(
		int $postUid, 
		?int $deletedBy, 
		bool $fileOnly, 
		bool $byProxy,
		?int $fileId = null
	): void {

		$query = "INSERT INTO {$this->deletedPostsTable} 
			(post_uid, deleted_by, file_only, by_proxy, file_id) 
			VALUES (:post_uid, :deleted_by, :file_only, :by_proxy, :file_id)";

		$parameters = [
			':post_uid'   => $postUid,
			':deleted_by' => $deletedBy,
			':file_only'  => (int)$fileOnly,
			':by_proxy'   => (int)$byProxy,
			':file_id'    => $fileId,   // leave NULL as NULL
		];

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
		$query .= " WHERE p.post_uid = :post_uid";

		// query parameteres
		$params = [
			':post_uid' => $postUid
		];

		// fetch the row
		$deletedPost = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment rows
		$deletedPost = mergeDeletedPostRows($deletedPost);

		// return result
		return $deletedPost[0] ?? false;
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

	public function removeOpenRows(int $postUid): void {
		// query to remove open post deletions under this post uid
		$query = "DELETE FROM {$this->deletedPostsTable} WHERE post_uid = :post_uid AND open_flag = 1";

		// parameter
		$params = [
			':post_uid' => $postUid
		];

		// execute query
		$this->databaseConnection->execute($query, $params);	
	}
}