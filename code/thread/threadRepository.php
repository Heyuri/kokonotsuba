<?php

class threadRepository {
	private array $allowedOrderFields;

	public function __construct(
		private DatabaseConnection $databaseConnection, 
		private string $postTable, 
		private string $threadTable, 
		private string $deletedPostsTable,
		private string $fileTable) {
		$this->allowedOrderFields = ['last_bump_time', 'last_reply_time', 'thread_created_time', 'post_op_number', 'number_of_posts'];
	}

	private function getBaseThreadQuery(bool $includeDeletedCount = true): string {
		$latestDel   = sqlLatestDeletionEntry($this->deletedPostsTable);
		$visibleCond = excludeDeletedPostsCondition('d');

		// conditional filter
		$countFilter = $includeDeletedCount
			? ""                    // include all posts
			: $visibleCond; // restrict to visible posts only

		$query = "
			SELECT 
				t.*,
				dp.open_flag AS thread_deleted,
				dp.file_only AS thread_attachment_deleted,
				dp.by_proxy,

				(
					SELECT COUNT(*)
					FROM {$this->postTable} p
					LEFT JOIN (
						{$latestDel}
					) d ON p.post_uid = d.post_uid
					WHERE p.thread_uid = t.thread_uid
					{$countFilter}
				) AS number_of_posts

			FROM {$this->threadTable} t
			LEFT JOIN (
				{$latestDel}
			) dp ON t.post_op_post_uid = dp.post_uid
		";

		return $query;
	}

	private function getBaseCountThreadQuery(): string {
		// get join clause
		$joinClause = $this->getBaseThreadJoinClause();
		
		// generate thread count query
		$query = "
			SELECT COUNT(thread_uid),
					t.*,					
					dp.open_flag AS thread_deleted,
					dp.file_only AS thread_attachment_deleted
			FROM {$this->threadTable} t
			$joinClause 
		";

		// return query
		return $query;
	}

	private function getBaseThreadJoinClause(): string {
		// join clause for thread data
		$joinClause = "
			LEFT JOIN (
			 	   SELECT dp1.post_uid, dp1.open_flag, dp1.file_only, dp1.by_proxy
			 	   FROM {$this->deletedPostsTable} dp1
			 	   INNER JOIN (
			 		   SELECT post_uid, MAX(deleted_at) AS max_deleted_at
			 		   FROM {$this->deletedPostsTable}
			 		   GROUP BY post_uid
			 	   ) dp2 ON dp1.post_uid = dp2.post_uid AND dp1.deleted_at = dp2.max_deleted_at
			) dp ON t.post_op_post_uid = dp.post_uid";
	
		// return join clause
		return $joinClause;
	}

	public function getThreadByUid(string $thread_uid, bool $includeDeleted = false): array|false {
		// get base thread query
		$query = $this->getBaseThreadQuery($includeDeleted);

		// append WHERE query
		// select thread by `thread_uid`
		$query .= " WHERE thread_uid = :thread_uid";

		$params = [':thread_uid' => (string) $thread_uid];
		
		$threadData = $this->databaseConnection->fetchOne($query, $params);	
	
		return $threadData;
	}

	public function mapThreadUidListToPostNumber($threadUidArray) {
		if (empty($threadUidArray)) {
			return array(); // early return if no thread UIDs
		}
	
		if (!is_array($threadUidArray)) {
			$threadUidArray = [$threadUidArray];
		}
		
		$placeholders = implode(',', array_fill(0, count($threadUidArray), '?'));
		$query = "SELECT post_op_number, boardUID FROM {$this->threadTable} WHERE thread_uid IN ($placeholders) ORDER BY last_bump_time DESC";
		
		return $this->databaseConnection->fetchAllAsArray($query, $threadUidArray);
	}

	public function fetchThreadUIDsByBoard(
		string $boardUID,
		int $start = 0,
		int $amount = 0,
		string $orderBy = 'last_bump_time',
		string $direction = 'DESC'): array {
			
		// Note: You should still validate $orderBy and $direction before calling this method in Service!

		// join latest deletion entry for the thread OP post, and exclude deleted threads
		$latestDeletionSQL = sqlLatestDeletionEntry($this->deletedPostsTable);
		$visibleCond = excludeDeletedPostsCondition('d');

		$query = "SELECT t.thread_uid
				FROM {$this->threadTable} t
				LEFT JOIN ({$latestDeletionSQL}) d
					ON d.post_uid = t.post_op_post_uid
				WHERE t.boardUID = :board_uid
					{$visibleCond}
				ORDER BY {$orderBy} {$direction}";

		if ($amount > 0) {
			$query .= " LIMIT {$start}, {$amount}";
		}

		$params = [':board_uid' => $boardUID];

		$threads = $this->databaseConnection->fetchAllAsIndexArray($query, $params);
		return !empty($threads) ? array_merge(...$threads) : [];
	}
	
	// insert a new thread into the thread table
	public function addThread($boardUID, $post_uid, $thread_uid, $post_op_number) {
		$query = "INSERT INTO {$this->threadTable} (boardUID, post_op_post_uid, post_op_number, thread_uid) VALUES (:board_uid, :post_op_post_uid, :post_op_number, :thread_uid)";
		$params = [
			':board_uid' => $boardUID,
			':post_op_post_uid' => $post_uid,
			':post_op_number' => $post_op_number,
			':thread_uid' => $thread_uid,
		];
		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * Batch insert threads and return auto-incremented post_op_post_uid values.
	 *
	 * @param array $threads Each item:
	 *   [
	 *      'board_uid' => int,
	 *      'post_op_number' => int,
	 *      'thread_uid' => string
	 *   ]
	 * @return array Returned post_op_post_uid values in same order
	 */
	public function addThreadsBatch(array $threads): array {
		if (empty($threads)) return [];

		$query = "INSERT INTO {$this->threadTable}
			(boardUID, post_op_post_uid, post_op_number, thread_uid)
			VALUES ";

		$values = [];
		$params = [];

		foreach ($threads as $i => $t) {
			$values[] = "(:board_uid_$i, 0, :post_op_number_$i, :thread_uid_$i)";
			$params[":board_uid_$i"] = $t['board_uid'];
			$params[":post_op_number_$i"] = $t['post_op_number'];
			$params[":thread_uid_$i"] = $t['thread_uid'];
		}

		$query .= implode(',', $values);

		// Execute insert
		$this->databaseConnection->execute($query, $params);

		// Retrieve the INSERT IDs
		$firstId = $this->databaseConnection->lastInsertId();
		$count = count($threads);

		return range($firstId, $firstId + $count - 1);
	}

	public function getLastThreadTimeFromBoard($board) {
		$boardUID = $board->getBoardUID();
		
		$query = "SELECT MAX(thread_created_time) FROM {$this->threadTable} WHERE boardUID = :boardUID";
		$params = [
			':boardUID' => $boardUID,
		];
		$lastThreadTime = $this->databaseConnection->fetchColumn($query, $params);
		return $lastThreadTime;
	}

	public function getFilteredThreadCount($filters = [], bool $includeDeleted = false) {
		// get base count query
		$query = $this->getBaseCountThreadQuery();

		// append WHERE clause
		// filter the whole db if not filtered
		$query .= " WHERE 1";

		// exclude the threads where the op post was deleted
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		$params = [];
		
		bindThreadFilterParameters($params, $query, $filters); //apply filtration to query
		
		$threads = $this->databaseConnection->fetchColumn($query, $params);
	
		return $threads;
	}

	public function fetchFilteredThreads(array $filters, string $order, int $amount, int $offset, bool $includeDeleted = false): array {
		// get thread query
		$query = $this->getBaseThreadQuery($includeDeleted);
	
		// apppend WHERE
		$query .= " WHERE 1";

		// exclude the threads where the op post was deleted
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		$params = [];

		if (!empty($filters['board']) && is_array($filters['board'])) {
			bindBoardUIDFilter($params, $query, $filters['board'], 't.boardUID');
		}

		$query .= " ORDER BY t.{$order} DESC LIMIT $amount OFFSET $offset";

		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function isThread($threadID) {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => strval($threadID)
		];
		$threadExists = $this->databaseConnection->fetchColumn($query, $params) ? true : false;
		return $threadExists;
	}

	public function resolveThreadUidFromResno($board, $resno) {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE post_op_number = :resno AND boardUID = :board_uid";
		$params = [
			':resno' => intval($resno),
			':board_uid' => $board->getBoardUID(),
		];
		$thread_uid = $this->databaseConnection->fetchColumn($query, $params);
		return $thread_uid;
	}

	public function resolveThreadNumberFromUID($thread_uid) {
		$query = "SELECT post_op_number FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => strval($thread_uid)
		];
		$threadNo = $this->databaseConnection->fetchColumn($query, $params);
		return $threadNo;
	}

	public function updateThreadBumpAndReplyTime(string $threadUID, $time): void {
		$this->databaseConnection->execute("
			UPDATE {$this->threadTable}
			SET last_bump_time = ?, last_reply_time = ?
			WHERE thread_uid = ?
		", [$time, $time, $threadUID]);
	}

	public function deleteThreadByUID(string $threadUID): void {
		$this->databaseConnection->execute("
			DELETE FROM {$this->threadTable}
			WHERE thread_uid = ?
		", [$threadUID]);
	}


	public function updateThreadReplyTime(string $threadUID, $time): void {
		$this->databaseConnection->execute("
			UPDATE {$this->threadTable}
			SET last_reply_time = ?
			WHERE thread_uid = ?
		", [$time, $threadUID]);
	}

		// get all OPs from threads
	public function getFirstPostsFromThreads(array $threadUIDs): array {
		if (empty($threadUIDs)) {
				return [];
		}

		// Ensure values are unique and sanitized
		$threadUIDs = array_map('strval', array_unique($threadUIDs));
		$placeholders = pdoPlaceholdersForIn($threadUIDs);

		// Subquery to get the first (oldest) post of each thread
		$query = "
			SELECT p.*
			FROM {$this->postTable} p
			INNER JOIN (
				SELECT thread_uid, MIN(post_uid) AS first_post_uid
				FROM {$this->postTable}
				WHERE thread_uid IN $placeholders
				GROUP BY thread_uid
			) first_posts
			ON p.thread_uid = first_posts.thread_uid AND p.post_uid = first_posts.first_post_uid
		";

		$results = $this->databaseConnection->fetchAllAsArray($query, $threadUIDs);

		// merge rows (in cases of multiple attachments)
		$results = mergeMultiplePostRows($results);

		// Index results by thread_uid for fast lookup
		$indexed = [];
		foreach ($results as $row) {
				$indexed[$row['thread_uid']] = $row;
		}

		return $indexed;
	}

	public function getOpPostUidsFromThreads(array $threadUIDs): array {
		if (empty($threadUIDs)) {
				return [];
		}

		$placeholders = pdoPlaceholdersForIn($threadUIDs);

		// Subquery to get the first (oldest) post of each thread
		$query = "
			SELECT post_op_post_uid
			FROM {$this->threadTable}
			WHERE thread_uid IN $placeholders
		";

		$results = $this->databaseConnection->fetchAllAsIndexArray($query, $threadUIDs);

		return array_merge(...$results);
	}

	/**
	 * Bump a discussion thread to the top.
	*/
	public function bumpThread(string $threadID): void {
		/*
			We fetch the latest post time using MAX(root).
			This gets the newest post's timestamp without needing ORDER BY or LIMIT.
		*/
		$query = "
			UPDATE {$this->threadTable}
			SET
				last_bump_time = (
					SELECT MAX(p.root)
					FROM {$this->postTable} p
					WHERE p.thread_uid = :thread_uid
				),
				last_reply_time = (
					SELECT MAX(p.root)
					FROM {$this->postTable} p
					WHERE p.thread_uid = :thread_uid
				)
			WHERE thread_uid = :thread_uid
		";

		$params = [
			':thread_uid' => $threadID
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function getThreadsFromBoard(
		int $boardUid,
		int $limit = 50,
		int $offset = 0,
		string $orderBy = 'last_bump_time',
		string $direction = 'DESC',
		bool $includeDeleted = false,
	): array|null {
		// sanitize limit and offset
		$limit = max(0, (int)$limit);
		$offset = max(0, (int)$offset);

		// santitize/validate direction
		$direction = strtoupper($direction);
		if ($direction !== 'ASC' && $direction !== 'DESC') {
			$direction = 'DESC';
		}

		// validate order by
		if (!in_array($orderBy, $this->allowedOrderFields)) {
			throw new RuntimeException("Invalid order by field.");
		}

		// build the base thread query (includes number_of_posts)
		$base = $this->getBaseThreadQuery($includeDeleted);

		// start full query
		$query = "
			SELECT *
			FROM (
				{$base}
			) t
			WHERE t.boardUID = :board_uid
		";

		// optionally exclude threads where OP is fully deleted
		if (!$includeDeleted) {
			// deleted = dp.open_flag = 0 AND dp.file_only = 0
			$query .= " AND (COALESCE(t.thread_deleted, 0) = 0 OR COALESCE(t.thread_attachment_deleted, 0) = 1)";
		}

		// add ordering (sticky first)
		$query .= " ORDER BY t.is_sticky DESC, t.{$orderBy} {$direction}";

		// add limits
		if ($limit) {
			$query .= " LIMIT {$limit} OFFSET {$offset}";
		}

		// add param
		$params = [':board_uid' => $boardUid];

		// return db query fetching the thread data
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}


	public function getPostsFromThread(string $threadUID, bool $includeDeleted = false, int $amount = 500, int $offset = 0): ?array {
		// sanitize numeric inputs
		$amount = max(0, (int)$amount);
		$offset = max(0, (int)$offset);

		// Generate the base query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// Add the condition specific to this method (fetching posts for a single thread)
		$query .= " WHERE p.thread_uid = :thread_uid";

		// If we do not want to include deleted posts, add the condition to exclude them
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		/*
			We UNION the OP (p.is_op = 1) with the replies (p.is_op = 0).
			The OP is always returned first and is not affected by reply LIMIT/OFFSET.
		*/
		$query = "
			(
				{$query}
				AND p.is_op = 1
				ORDER BY p.post_uid ASC
			)
			UNION ALL
			(
				" . getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable) . "
				WHERE p.thread_uid = :thread_uid
				" . (!$includeDeleted ? excludeDeletedPostsCondition() : "") . "
				AND p.is_op = 0
				ORDER BY p.post_uid ASC
				LIMIT $amount OFFSET $offset
			)
		";

		$params = [
			':thread_uid' => $threadUID,
		];

		// fetch post rows
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];

		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		// return null if posts is false/null
		if(!$posts) {
			return null;
		}
		else {
			// return results
			return $posts;
		}
	}

	public function getAllPostsFromThread(string $threadUID, bool $includeDeleted = false): ?array {
		// Generate the base query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// Add thread condition
		$query .= " WHERE p.thread_uid = :thread_uid";

		// Exclude deleted posts if requested
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		// Order results by post id
		$query .= " ORDER BY p.post_uid ASC";

		$params = [
			':thread_uid' => $threadUID
		];

		// Fetch rows
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];

		// Merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		// Return null when empty
		if(!$posts) {
			return null;
		}

		// Return all posts
		return $posts;
	}

	public function getPostsForThreads(array $threadUIDs, int $previewCount, bool $includeDeleted = false): array {
		if (empty($threadUIDs)) return [];

		// add to preview count by 1 so we include OP
		$previewCount++;

		// Generate the base query
		$base = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// Add the condition specific to this method (fetching posts for multiple threads)
		$inClause = pdoPlaceholdersForIn($threadUIDs);
		$base .= " WHERE p.thread_uid IN $inClause";

		// If we do not want to include deleted posts, add the condition to exclude them
		if(!$includeDeleted) {
			$base .= excludeDeletedPostsCondition();
		}

		// wrap with dense_rank partition to limit posts per thread (OP + preview replies)
		$query = "
			SELECT *
			FROM (
				SELECT
					t.*,
					DENSE_RANK() OVER (
						PARTITION BY t.thread_uid
						ORDER BY t.is_op DESC, t.post_uid DESC
					) AS rn
				FROM (
					$base
				) t
			) x
			WHERE x.rn <= ?
			ORDER BY x.no ASC
		";

		// fetch posts
		$params = array_merge($threadUIDs, [$previewCount]);
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];

		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		// return results
		return $posts;
	}

	/* Get number of discussion threads */
	public function threadCountFromBoard(board $board, bool $includeDeleted = false) {
		// get board uid
		$boardUid = $board->getBoardUID();

		// get base count query
		$query = $this->getBaseCountThreadQuery();

		// append WHERE query
		// filter by boardUID
		$query .= " WHERE t.boardUID = :board_uid";

		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}
		
		// params
		$params = [
			':board_uid' => $boardUid,
		];

		// fetch column from db
		$threadCount = $this->databaseConnection->fetchValue($query, $params);

		// return threadCount
		return $threadCount;
	}

	public function getPostCountFromThread($threadUID) {
		if(!$threadUID) throw new Exception("Invalid thread UID in ".__METHOD__);
		$query = "SELECT COUNT(post_uid) FROM {$this->postTable} WHERE thread_uid = ?";
		$count = $this->databaseConnection->fetchColumn($query, [$threadUID]);
		return $count;
	}

	public function updateThreadLastReplyTime($threadID) {
		$query = "UPDATE {$this->threadTable} SET last_reply_time = CURRENT_TIMESTAMP WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $threadID
		]; 
	
		$this->databaseConnection->execute($query, $params);
	}

	public function insertThread($threadUid, $postOpNumber, $boardUID) {
		$query = "INSERT INTO {$this->threadTable} (
			thread_uid, post_op_number, post_op_post_uid, boardUID
		) VALUES (
			:thread_uid, :post_op_number, -1, :boardUID
		)";
		$this->databaseConnection->execute($query, [
			':thread_uid'		=> $threadUid,
			':post_op_number'	=> $postOpNumber,
			':boardUID'			=> $boardUID
		]);
	}
	
	public function updateThreadOpPostUid(string $threadUid, string $postOpUid): void {
		$this->databaseConnection->execute(
			"UPDATE {$this->threadTable}
			 SET post_op_post_uid = :post_uid
			 WHERE thread_uid = :thread_uid",
			[
				':post_uid'		=> $postOpUid,
				':thread_uid'	=> $threadUid
			]
		);
	}

	public function updatePostForBoardMove($postUid, $newPostNumber, $newBoardUID, $updatedCom): void {
		$query = "UPDATE {$this->postTable} 
				  SET no = :new_no, boardUID = :new_boardUID, com = :updated_com 
				  WHERE post_uid = :post_uid";
		$params = [
			':new_no' => intval($newPostNumber),
			':new_boardUID' => intval($newBoardUID),
			':updated_com' => $updatedCom,
			':post_uid' => $postUid,
		];
		$this->databaseConnection->execute($query, $params);
	}

	public function updateThreadForBoardMove($threadUid, $newBoardUID, $newPostOpNumber): void {
		$query = "UPDATE {$this->threadTable} 
					SET boardUID = :new_boardUID, post_op_number = :new_post_op_number
					WHERE thread_uid = :thread_uid";
		$params = [
			':new_boardUID' => intval($newBoardUID),
			':new_post_op_number' => intval($newPostOpNumber),
			':thread_uid' => $threadUid,
		];
		
		$this->databaseConnection->execute($query, $params);
	}

	public function stickyThread(string $thread_uid): void {
		$query = "UPDATE {$this->threadTable}
					SET is_sticky = TRUE
					WHERE thread_uid = :thread_uid";
		
		$params = [
			':thread_uid' => $thread_uid
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function unstickyThread(string $thread_uid): void {
		$query = "UPDATE {$this->threadTable}
					SET is_sticky = FALSE
					WHERE thread_uid = :thread_uid";
		
		$params = [
			':thread_uid' => $thread_uid
		];

		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * Update the post_op_post_uid in the thread table for a given thread.
	 *
	 * @param string $threadUid The unique identifier for the thread
	 * @param int $postOpPostUid The post UID for the thread OP (original post)
	 * @return void
	 */
	public function updatePostOpUid(string $threadUid, int $postOpPostUid): void {
		// Prepare the query to update the post_op_post_uid for the specific thread
		$query = "UPDATE {$this->threadTable} 
				SET post_op_post_uid = :postOpPostUid
				WHERE thread_uid = :threadUid";

		// Parameters to bind to the query
		$params = [
			':postOpPostUid' => $postOpPostUid,
			':threadUid' => $threadUid
		];

		// Execute the query
		$this->databaseConnection->execute($query, $params);
	}

	public function getPageOfThread(string $threadUid, int $threadsPerPage): null|false|int {
		// get the query to get the page of the thread
		$query = "
			SELECT CEIL(
				(
					SELECT COUNT(*)
					FROM {$this->threadTable} t2
					WHERE t2.last_bump_time <= t1.last_bump_time
				) / :threads_per_page
			) AS page
			FROM {$this->threadTable} t1
			WHERE t1.thread_uid = :thread_uid
		";

		// bind param
		$params = [
			':thread_uid' => $threadUid,
			':threads_per_page' => $threadsPerPage,
		];

		// fetch value
		$threadPage = $this->databaseConnection->fetchValue($query, $params);

		// return it
		return $threadPage;
	}
}