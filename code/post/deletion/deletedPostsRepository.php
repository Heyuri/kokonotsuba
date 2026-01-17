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
		$query = "
			UPDATE {$this->deletedPostsTable} dp
			INNER JOIN {$this->postTable} p ON p.post_uid = dp.post_uid
			SET dp.restored_at = CURRENT_TIMESTAMP,
				dp.restored_by = :account_id,
				dp.file_only = 0
			WHERE p.thread_uid = :thread_uid
			AND (dp.by_proxy = 1
			OR p.is_op = 1)
		";

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
				FROM {$this->deletedPostsTable}
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
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable, true);
		
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
		$postData = mergeMultiplePostRows($postData);

		// return it
		return $postData[0] ?? false;
	}

	public function getPostsByIdList(array $postUids): array|false {
		// base query to get the posts data by deleted post id
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable, true);
		
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
		$posts = mergeMultiplePostRows($posts);

		// return posts
		return $posts;
	}

	/**
	 * Retrieve a paginated list of deleted posts. Pagination is done by post_uid
	 * (logical posts), not by individual deleted_posts rows. For each post_uid on
	 * the page, we load all related deleted_posts rows (post-level + attachment-level)
	 * and then merge them.
	 *
	 * @param int      $amount       Number of top-level posts to return.
	 * @param int      $offset       Pagination offset.
	 * @param string   $orderBy      Column to order by (validated).
	 * @param string   $direction    ASC/DESC.
	 * @param bool     $restoredOnly Whether to show restored instead of open.
	 * @param int|null $accountId    Optional filter for deleted_by.
	 *
	 * @return array|false           Merged deleted-post entries or false if none.
	 */
	public function getPagedEntries(
		int $amount,
		int $offset,
		string $orderBy = 'id',
		string $direction = 'DESC',
		bool $restoredOnly = false,
		?int $accountId = null
	): array|false {

		// Validate ordering field
		if (!in_array($orderBy, $this->allowedOrderFields, true)) {
			$orderBy = 'id';
		}

		// Validate direction
		$direction = strtoupper($direction);
		if (!in_array($direction, ['ASC', 'DESC'], true)) {
			$direction = 'DESC';
		}

		// Parameters get accumulated here
		$params = [];

		// Step 1: build WHERE clause for deleted_posts
		$filterClause = $this->buildDeletedPostsFilter($restoredOnly, $accountId, $params);

		// Step 2: get a page of post_uid values representing logical deleted posts
		$postUids = $this->getPagedPostUids(
			$amount,
			$offset,
			$orderBy,
			$direction,
			$filterClause,
			$params
		);

		if (!$postUids) {
			return false;
		}

		// Step 3: fetch all deleted_posts rows for those post_uids
		$rows = $this->fetchDeletedPostsForUids(
			$postUids,
			$filterClause,
			$orderBy,
			$direction,
			$params
		);

		// Step 4: merge into proper post entries with attachments + deletion metadata
		$merged = mergeDeletedPostRows($rows);

		return $merged ?: false;
	}

	/**
	 * Build the WHERE clause that filters deleted_posts rows.
	 *
	 * This determines whether we show open or restored entries, and optionally
	 * filters by which account deleted the entry. The method appends parameters
	 * into $params by reference.
	 *
	 * @param bool      $restoredOnly  If true, return only restored rows; otherwise only open rows.
	 * @param int|null  $accountId     Optional filter for dp.deleted_by.
	 * @param array     $params        Parameter array passed by reference.
	 *
	 * @return string   A complete WHERE clause beginning with "WHERE".
	 */
	private function buildDeletedPostsFilter(bool $restoredOnly, ?int $accountId, array &$params): string {
		$parts = [];

		// Choose open vs restored deleted entries.
		if (!$restoredOnly) {
			$parts[] = 'dp.open_flag = 1';	// include only non-restored
		} else {
			$parts[] = 'dp.open_flag = 0';	// include only restored
		}

		// Always exclude proxy records.
		$parts[] = 'dp.by_proxy = 0';

		// Optionally filter by the deleting account.
		if ($accountId !== null) {
			$parts[] = 'dp.deleted_by = :account_id';
			$params[':account_id'] = $accountId;
		}

		// Build final WHERE string.
		if (!$parts) {
			return '';
		}

		return ' WHERE ' . implode(' AND ', $parts);
	}

	/**
	 * Retrieve a single "page" of post_uid values. Each post_uid represents one
	 * logical deleted-post entry for the UI. We group deleted_posts rows by post_uid
	 * and order by a chosen field so pagination is stable.
	 *
	 * @param int    $amount       Number of entries to load.
	 * @param int    $offset       Starting offset.
	 * @param string $orderBy      Column name to order by (validated beforehand).
	 * @param string $direction    ASC or DESC.
	 * @param string $filterClause The WHERE clause generated by buildDeletedPostsFilter().
	 * @param array  $params       Bound parameters for the query.
	 *
	 * @return array|false         A flat array of post_uid values, or false if none.
	 */
	private function getPagedPostUids(
		int $amount,
		int $offset,
		string $orderBy,
		string $direction,
		string $filterClause,
		array $params
	): array|false {

		// Map orderBy field into the appropriate grouped expression.
		switch ($orderBy) {
			case 'post_uid':
				$sortExpr = 'dp.post_uid';
				break;
			case 'deleted_at':
				$sortExpr = 'MAX(dp.deleted_at)';
				break;
			case 'restored_at':
				$sortExpr = 'MAX(dp.restored_at)';
				break;
			case 'id':
			default:
				$sortExpr = 'MAX(dp.id)';
				break;
		}

		// Query returns one post_uid per logical deleted entry.
		$query = "
			SELECT dp.post_uid
			FROM {$this->deletedPostsTable} dp
			{$filterClause}
			GROUP BY dp.post_uid
			ORDER BY {$sortExpr} {$direction}
			LIMIT {$amount} OFFSET {$offset}
		";

		// Returns structured array like [ ['post_uid' => 123], ['post_uid' => 456] ]
		$rows = $this->databaseConnection->fetchAllAsIndexArray($query, $params);

		if (!$rows) {
			return false;
		}

		// Flatten into [123, 456, ...]
		return array_merge(...$rows);
	}

	/**
	 * Given a page of post_uid values, fetch all deleted_posts rows (post-level
	 * and attachment-level) for those posts. Then the caller merges them.
	 *
	 * @param array  $postUids     Array of post_uid integers.
	 * @param string $filterClause WHERE clause for deleted_posts.
	 * @param string $orderBy      Validated order-by field.
	 * @param string $direction    ASC/DESC.
	 * @param array  $params       Parameters including filters from step 1.
	 *
	 * @return array               Raw SQL rows for merging.
	 */
	private function fetchDeletedPostsForUids(
		array $postUids,
		string $filterClause,
		string $orderBy,
		string $direction,
		array $params
	): array {

		// Build named placeholders for dp.post_uid IN (...)
		$named = pdoNamedPlaceholdersForIn($postUids, 'uid');
		$inClause = $named['placeholders'];

		// Merge IN parameters with filter parameters
		$params = array_merge($params, $named['params']);

		// Base SELECT/JOIN for deleted-posts rows
		$query = $this->getBaseDeletedPostsQuery();

		// Apply filters + restrict to the selected post_uid list
		if ($filterClause) {
			$query .= " {$filterClause} AND dp.post_uid IN ($inClause)";
		} else {
			$query .= " WHERE dp.post_uid IN ($inClause)";
		}

		// Deterministic ordering of the detailed rows
		$query .= " ORDER BY dp.{$orderBy} {$direction}, dp.id {$direction}";

		// Retrieve all rows for mergeMultiplePostRows()
		return $this->databaseConnection->fetchAllAsArray($query, $params);
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
		$deletedPost = mergeMultiplePostRows($deletedPost);

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
				dp.note				 AS deleted_note,

				-- Post data (may be null if the post itself is gone)
				p.*,
				-- thread op number
				t.post_op_number,

				-- Attachment belonging to THIS deletion event or, for post-level,
				-- all attachments belonging to the post.
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

			-- Attachments:
			--  - for attachment-only deletion: match specific file_id
			--  - for post-level deletion (file_id IS NULL): match all files of the post
			LEFT JOIN {$this->fileTable} f
				ON (
					(dp.file_id IS NOT NULL AND f.id = dp.file_id)
					OR (dp.file_id IS NULL AND f.post_uid = dp.post_uid)
				)

			LEFT JOIN {$this->accountTable} da ON dp.deleted_by = da.id
			LEFT JOIN {$this->accountTable} ra ON dp.restored_by = ra.id

			LEFT JOIN {$this->threadTable} t on p.thread_uid = t.thread_uid
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
		?int $fileId = null,
		?int $restoredBy = null, 
		?string $note = null
	): void {

		$query = "INSERT INTO {$this->deletedPostsTable} 
			(post_uid, deleted_by, file_only, by_proxy, restored_by, note, file_id) 
			VALUES (:post_uid, :deleted_by, :file_only, :by_proxy, :restored_by, :note, :file_id)";

		$parameters = [
			':post_uid'   => $postUid,
			':deleted_by' => $deletedBy,
			':file_only'  => (int)$fileOnly,
			':by_proxy'   => (int)$byProxy,
			':restored_by'=> $restoredBy,
			':note'		  => $note,
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
		$query .= " WHERE p.post_uid = :post_uid ORDER BY dp.id DESC LIMIT 1";

		// query parameteres
		$params = [
			':post_uid' => $postUid
		];

		// fetch the row
		$deletedPost = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment rows
		$deletedPost = mergeMultiplePostRows($deletedPost);

		// return result
		return $deletedPost[0] ?? false;
	}

	public function getDeletedPostRowByFileId(int $fileId): false|array {
		// query to fetch the deleted post by post uid
		$query = $this->getBaseDeletedPostsQuery();

		// select the post by post uid
		$query .= " WHERE dp.file_id = :file_id ORDER BY dp.id DESC LIMIT 1";

		// query parameteres
		$params = [
			':file_id' => $fileId
		];

		// fetch the row
		$deletedPost = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment rows
		$deletedPost = mergeMultiplePostRows($deletedPost);

		// return result
		return $deletedPost[0] ?? false;
	}

	public function getExpiredEntryIDs(int $timeLimit, bool $attachmentsOnly = false): false|array {
		// query to get entries older than the time limit (in hours) 
		$query = "SELECT id
			FROM {$this->deletedPostsTable}
			WHERE deleted_at < NOW() - INTERVAL {$timeLimit} HOUR
			AND COALESCE(open_flag, 0) = 1";

		// if we only want the attachments then append a condition to get attachment-level deletions 
		if($attachmentsOnly) {
			// append condition for file_only = 1
			$query .= " AND file_only = 1";
		}
		// otherwise filter for post level deletion
		else {
			// append condition for file_only = 0
			$query .= " AND file_only = 0";
		}

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
		$query = "DELETE FROM {$this->deletedPostsTable} WHERE post_uid = :post_uid AND open_flag = 1 AND file_only = 0";

		// parameter
		$params = [
			':post_uid' => $postUid
		];

		// execute query
		$this->databaseConnection->execute($query, $params);	
	}

	private function buildMapSql(array $oldValues, array $newValues, string $oldKey, string $newKey, string $paramPrefix, array &$params): string {
		// This helper builds a temporary inline table using UNION ALL.
		// Each row maps one old identifier to its new counterpart.
		// Example:
		//   SELECT :p_old_0 AS old_uid, :p_new_0 AS new_uid
		//   UNION ALL
		//   SELECT :p_old_1 AS old_uid, :p_new_1 AS new_uid
		// The caller provides the desired column names and the parameter prefix.
		// All parameters are placed into the referenced params array for the query execution.
		$parts = [];

		foreach ($oldValues as $i => $oldVal) {
			// Retrieve the corresponding new value using the same index
			$newVal = $newValues[$i];

			// Create a SELECT row for this mapping pair
			$parts[] =
				"SELECT :{$paramPrefix}_old_{$i} AS {$oldKey}, :{$paramPrefix}_new_{$i} AS {$newKey}";

			// Attach the parameter values to the outgoing $params array
			$params[":{$paramPrefix}_old_{$i}"] = $oldVal;
			$params[":{$paramPrefix}_new_{$i}"] = $newVal;
		}

		// Join all SELECT statements with UNION ALL to form an inline table
		return implode("\nUNION ALL\n", $parts);
	}

	private function insertPostLevelDeletions(array $oldPostUids, array $newPostUids): void {
		// This method handles deletion records that belong to entire posts (no file_id).
		// It replicates past deletions for new posts created during a copy/clone action.

		// First, build the mapping between old and new post UIDs
		$postParams = [];
		$postMapSql = $this->buildMapSql($oldPostUids, $newPostUids, 'old_uid', 'new_uid', 'p', $postParams);

		// The SQL inserts into the deletion table by selecting from the old records
		// and replacing each old post UID with the corresponding new UID.
		$sql = "
			INSERT INTO {$this->deletedPostsTable} (
				post_uid,
				deleted_by,
				deleted_at,
				file_only,
				by_proxy,
				note,
				restored_at,
				restored_by,
				file_id
			)
			SELECT
				pm.new_uid,         -- new post UID
				dp.deleted_by,       -- copy metadata from old deletion entry
				dp.deleted_at,
				dp.file_only,
				dp.by_proxy,
				dp.note,
				dp.restored_at,
				dp.restored_by,
				NULL                 -- file_id remains NULL at post level
			FROM ({$postMapSql}) AS pm
			INNER JOIN {$this->deletedPostsTable} dp
				ON dp.post_uid = pm.old_uid  -- match old post UID
			WHERE dp.file_id IS NULL         -- only post-level deletions
		";

		// Execute the insert with the parameterized mapping
		$this->databaseConnection->execute($sql, $postParams);
	}

	private function insertFileLevelDeletions(
		array $oldPostUids,
		array $newPostUids,
		array $oldFileIDs,
		array $newFileIDs
	): void {
		// This method handles file-level deletion records (dp.file_id IS NOT NULL).
		// It replicates deletion states for each attachment linked to the copied posts.

		// First build file ID mapping inline table
		$fileParams = [];
		$fileMapSql = $this->buildMapSql($oldFileIDs, $newFileIDs, 'old_fid', 'new_fid', 'f', $fileParams);

		// Then build post UID mapping for file's parent post lookups
		$postParams = [];
		$postMapSql = $this->buildMapSql($oldPostUids, $newPostUids, 'old_uid', 'new_uid', 'p2', $postParams);

		// Merge parameters for both mapping sources
		$params = array_merge($fileParams, $postParams);

		// The SQL replicates file-level deletions.
		// It joins:
		//    fm (file mapping) to map file IDs
		//    dp (deletedPosts table) to reuse deletion metadata
		//    f  (files table) so we know which post a file belonged to
		//    pm (post mapping) so we can map the old post UID from f.post_uid to the new one
		$sql = "
			INSERT INTO {$this->deletedPostsTable} (
				post_uid,
				deleted_by,
				deleted_at,
				file_only,
				by_proxy,
				note,
				restored_at,
				restored_by,
				file_id
			)
			SELECT
				pm.new_uid,         -- new owning post UID for the file
				dp.deleted_by,       -- copy deletion metadata from old record
				dp.deleted_at,
				dp.file_only,
				dp.by_proxy,
				dp.note,
				dp.restored_at,
				dp.restored_by,
				fm.new_fid          -- new file ID
			FROM ({$fileMapSql}) AS fm
			INNER JOIN {$this->deletedPostsTable} dp
				ON dp.file_id = fm.old_fid      -- match old file-level deletion entry
			INNER JOIN {$this->fileTable} f
				ON f.id = dp.file_id            -- find old file's post UID
			INNER JOIN ({$postMapSql}) AS pm
				ON pm.old_uid = f.post_uid      -- map old post UID to new post UID
		";

		// Execute the insert with the merged parameters
		$this->databaseConnection->execute($sql, $params);
	}

	public function copyDeletionEntries(
		array $oldPostUids,
		array $newPostUids,
		array $oldFileIDs,
		array $newFileIDs
	): void {

		/*
			POST LEVEL DELETIONS
			(file_id IS NULL)

			The following block copies deletion entries tied directly to posts,
			such as when a post was soft-deleted.
		*/
		if (!empty($oldPostUids)) {
			$this->insertPostLevelDeletions($oldPostUids, $newPostUids);
		}

		/*
			ATTACHMENT LEVEL DELETIONS
			(file_id IS NOT NULL)

			This block handles the replication of deletion entries for attached files.
			It ensures that each copied file inherits prior deletion metadata.
		*/
		if (!empty($oldFileIDs)) {
			$this->insertFileLevelDeletions($oldPostUids, $newPostUids, $oldFileIDs, $newFileIDs);
		}
	}


}