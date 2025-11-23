<?php

class threadRepository {
	private array $allowedOrderFields;

	public function __construct(
		private DatabaseConnection $databaseConnection, 
		private string $postTable, 
		private string $threadTable, 
		private string $deletedPostsTable,
		private string $fileTable) {
		$this->allowedOrderFields = ['last_bump_time', 'last_reply_time', 'thread_created_time', 'post_op_number'];
	}

	private function getBaseThreadQuery(): string {
		// generate thread query
		$query = "
				SELECT 
					t.*,
					dp.open_flag AS thread_deleted,
					dp.file_only AS thread_attachment_deleted,
					dp.by_proxy
				FROM {$this->threadTable} t
				LEFT JOIN (
			 	   SELECT dp1.post_uid, dp1.open_flag, dp1.file_only, dp1.by_proxy
			 	   FROM {$this->deletedPostsTable} dp1
			 	   INNER JOIN (
			 		   SELECT post_uid, MAX(deleted_at) AS max_deleted_at
			 		   FROM {$this->deletedPostsTable}
			 		   GROUP BY post_uid
			 	   ) dp2 ON dp1.post_uid = dp2.post_uid AND dp1.deleted_at = dp2.max_deleted_at
				) dp ON t.post_op_post_uid = dp.post_uid
		";

		// return query
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

	public function getThreadByUid(string $thread_uid): array|false {
		// get base thread query
		$query = $this->getBaseThreadQuery();

		// append WHERE query
		// select thread by `thread_uid`
		$query .= " WHERE thread_uid = :thread_uid";

		$params = [':thread_uid' => (string) $thread_uid];
		
		$threadData = $this->databaseConnection->fetchOne($query, $params);	
	
		return $threadData;
	}

	/* Get all threads from all boards */
	public function getAllThreads() {
		$query = "SELECT * FROM {$this->threadTable} ORDER BY last_bump_time DESC";
		$threads = $this->databaseConnection->fetchAllAsArray($query);
		return $threads;
	}
	
	/* Get all thread uids from all boards */
	public function getAllThreadUIDs() {
		$query = "SELECT thread_uid FROM {$this->threadTable} ORDER BY last_bump_time DESC";
		$threads = $this->databaseConnection->fetchAllAsIndexArray($query);
		return array_merge(...$threads);
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

		$query = "SELECT thread_uid FROM {$this->threadTable}
				  WHERE boardUID = :board_uid
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
			$query = excludeDeletedPostsCondition($query);
		}

		$params = [];
		
		bindThreadFilterParameters($params, $query, $filters); //apply filtration to query
		
		$threads = $this->databaseConnection->fetchColumn($query, $params);
	
		return $threads;
	}

	public function fetchFilteredThreads(array $filters, string $order, int $amount, int $offset, bool $includeDeleted = false): array {
		// get thread query
		$query = $this->getBaseThreadQuery();
	
		// apppend WHERE
		$query .= " WHERE 1";

		// exclude the threads where the op post was deleted
		if(!$includeDeleted) {
			$query = excludeDeletedPostsCondition($query);
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
		$posts = $this->getPostsFromThread($threadID);

		if (empty($posts)) return;
	
		$lastPost = end($posts);
		$bumpTime = $lastPost['root']; // must be MySQL datetime string
	
		$query = "UPDATE {$this->threadTable}
					SET last_bump_time = :bump_time,
					last_reply_time = :bump_time
				WHERE thread_uid = :thread_uid";
	
		$params = [
			':bump_time'  => $bumpTime,
			':thread_uid' => $threadID
		];
	
		$this->databaseConnection->execute($query, $params);
	}

	public function getThreadsFromBoard(int $boardUid, int $limit = 50, int $offset = 0, string $orderBy = 'last_bump_time', string $direction = 'DESC', bool $includeDeleted = false): array|null {
		// Ensure limit and offset are non-negative integers
		$limit = max(0, (int)$limit);
		$offset = max(0, (int)$offset);

		// Sanitize and validate direction
		$direction = strtoupper($direction);
		if ($direction !== 'ASC' && $direction !== 'DESC') {
			$direction = 'DESC'; // Default direction
		}

		if(!in_array($orderBy, $this->allowedOrderFields)) {
			throw new RuntimeException("Invalid order by field.");
		}

		// Construct the SQL query with ORDER BY, LIMIT, and OFFSET
		$query = "
			SELECT * 
			FROM {$this->threadTable} t
			LEFT JOIN (
				SELECT dp1.post_uid, dp1.open_flag, dp1.file_only, dp1.by_proxy
				FROM {$this->deletedPostsTable} dp1
				INNER JOIN (
					SELECT post_uid, MAX(deleted_at) AS max_deleted_at
					FROM {$this->deletedPostsTable}
					GROUP BY post_uid
				) dp2 ON dp1.post_uid = dp2.post_uid AND dp1.deleted_at = dp2.max_deleted_at
			) dp ON t.post_op_post_uid = dp.post_uid
			WHERE boardUID = :board_uid 
		";

		// exclude the threads where the op post was deleted
		if(!$includeDeleted) {
			$query = excludeDeletedPostsCondition($query);
		}

		// add thread order
		$query .= " ORDER BY is_sticky DESC, $orderBy $direction";

		// add thread limit
		if($limit) {
			$query .= " LIMIT {$limit} OFFSET {$offset}";
		}

		// Bind parameters to prevent SQL injection
		$params = [':board_uid' => $boardUid];

		// Execute the query and return results as an array
		$threads = $this->databaseConnection->fetchAllAsArray($query, $params);

		return $threads;
	}

	public function getPostsFromThread(string $threadUID, bool $includeDeleted = false): ?array {
		// Generate the base query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// Add the condition specific to this method (fetching posts for a single thread)
		$query .= " WHERE p.thread_uid = :thread_uid";

		// If we do not want to include deleted posts, add the condition to exclude them
		if(!$includeDeleted) {
			$query = excludeDeletedPostsCondition($query);
		}

		// append ORDER BY clause
		$query .= " ORDER BY p.post_uid ASC";

		$params = [':thread_uid' => $threadUID];

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

	public function getPostsForThreads(array $threadUIDs, bool $includeDeleted = false): array {
		if (empty($threadUIDs)) return [];

		// Generate the base query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// Add the condition specific to this method (fetching posts for multiple threads)
		$inClause = pdoPlaceholdersForIn($threadUIDs);
		$query .= " WHERE p.thread_uid IN $inClause";

		// If we do not want to include deleted posts, add the condition to exclude them
		if(!$includeDeleted) {
			$query = excludeDeletedPostsCondition($query);
		}

		// add adirection/order
		$query .= " ORDER BY p.no ASC";

		// fetch posts
		$posts = $this->databaseConnection->fetchAllAsArray($query, $threadUIDs) ?? [];

		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		// return results
		return $posts;
	}

	public function getAllAttachmentsFromThread($thread_uid) {
		$query = "SELECT ext, tim, boardUID FROM {$this->postTable} WHERE thread_uid = :thread_uid";
		$params[':thread_uid'] = $thread_uid;

		$threadAttachments = $this->databaseConnection->fetchAllAsArray($query, $params);
		return $threadAttachments;
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
			$query = excludeDeletedPostsCondition($query);
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

	public function getPostCountsForThreads(array $threadUIDs): array {
		if (empty($threadUIDs)) return [];

		$placeholders = implode(',', array_fill(0, count($threadUIDs), '?'));

		$query = "
			SELECT thread_uid, COUNT(post_uid) AS post_count
			FROM {$this->postTable}
			WHERE thread_uid IN ($placeholders)
			GROUP BY thread_uid
		";

		$results = $this->databaseConnection->fetchAllAsArray($query, $threadUIDs);

		$postCounts = [];
		foreach ($results as $row) {
				$postCounts[$row['thread_uid']] = (int)$row['post_count'];
		}

		return $postCounts;
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
}