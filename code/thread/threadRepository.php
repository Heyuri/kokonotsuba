<?php

class threadRepository {
	private string $postTable;
	private string $threadTable;
	private array $allowedOrderFields;
	private DatabaseConnection $databaseConnection;

	public function __construct(DatabaseConnection $databaseConnection, string $postTable, string $threadTable) {
		$this->databaseConnection = $databaseConnection;
		$this->postTable = $postTable;
		$this->threadTable = $threadTable;
		$this->allowedOrderFields = ['last_bump_time', 'last_reply_time', 'thread_created_time', 'post_op_number'];
	}

	public function getThreadByUid(string $thread_uid): array|false {
		$query = "SELECT * FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
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

	public function isThreadOP($post_uid) {
		$query = "SELECT post_op_post_uid FROM {$this->threadTable} WHERE post_op_post_uid = :post_op_post_uid";
		$params = [
			':post_op_post_uid' => strval($post_uid)
		];
		$threadExists = $this->databaseConnection->fetchColumn($query, $params) ? true : false;
		return $threadExists;
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

	public function getFilteredThreadCount($filters = []) {
		$query = "SELECT COUNT(thread_uid) FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		bindThreadFilterParameters($params, $query, $filters); //apply filtration to query
		
		$threads = $this->databaseConnection->fetchColumn($query, $params);
	
		return $threads;
	}

	public function fetchFilteredThreads(array $filters, string $order, int $amount, int $offset): array {
		$query = "
			SELECT t.thread_uid, t.post_op_post_uid, t.thread_created_time, t.last_bump_time, t.last_reply_time, 
				   t.post_op_number, p.status, t.boardUID
			FROM {$this->threadTable} t
			JOIN {$this->postTable} p ON t.post_op_post_uid = p.post_uid
			WHERE 1";
	
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

	public function deleteThreadsByOpPostUIDs(string $postUIDsList): void {
		$this->databaseConnection->execute("
			DELETE FROM {$this->threadTable}
			WHERE post_op_post_uid IN ({$postUIDsList})
		");
	}


	public function deleteThreadByUID(string $threadUID): void {
		$this->databaseConnection->execute("
			DELETE FROM {$this->threadTable}
			WHERE thread_uid = ?
		", [$threadUID]);
	}


	public function deleteThreadsByUidList(array $threadUidList): void {
		$inClause = pdoPlaceholdersForIn($threadUidList);
		
		$this->databaseConnection->execute("
			DELETE FROM {$this->threadTable}
			WHERE thread_uid IN $inClause", $threadUidList);
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
		$placeholders = implode(',', array_fill(0, count($threadUIDs), '?'));

		// Subquery to get the first (oldest) post of each thread
		$query = "
			SELECT p.*
			FROM {$this->postTable} p
			INNER JOIN (
				SELECT thread_uid, MIN(post_uid) AS first_post_uid
				FROM {$this->postTable}
				WHERE thread_uid IN ($placeholders)
				GROUP BY thread_uid
			) first_posts
			ON p.thread_uid = first_posts.thread_uid AND p.post_uid = first_posts.first_post_uid
		";

		$results = $this->databaseConnection->fetchAllAsArray($query, $threadUIDs);

		// Index results by thread_uid for fast lookup
		$indexed = [];
		foreach ($results as $row) {
				$indexed[$row['thread_uid']] = $row;
		}

		return $indexed;
	}


	// get all replies from a thread
	public function getAllPostsFromThreads(array $threadUIDs): array {
		if (empty($threadUIDs)) {
			return [];
		}
	
		// Ensure values are unique and sanitized
		$threadUIDs = array_map('strval', array_unique($threadUIDs));
		$placeholders = implode(',', array_fill(0, count($threadUIDs), '?'));
	
		// Fetch all posts for the given threads
		$query = "
			SELECT p.*
			FROM {$this->postTable} p
			WHERE p.thread_uid IN ($placeholders)
			ORDER BY p.thread_uid, p.post_uid
		";
	
		$results = $this->databaseConnection->fetchAllAsArray($query, $threadUIDs);
	
		// Group all posts by thread_uid
		$grouped = [];
		foreach ($results as $row) {
			$threadUID = $row['thread_uid'];
			if (!isset($grouped[$threadUID])) {
				$grouped[$threadUID] = [];
			}
			$grouped[$threadUID][] = $row;
		}
	
		return $grouped;
	}

		/**
	* Bump a discussion thread to the top.
	*/
	public function bumpThread(string $threadID, bool $sticky = false): void {
		$posts = $this->getPostsFromThread($threadID);
		if (empty($posts)) return;
	
		if ($sticky) {
			// Use MySQL's NOW() + INTERVAL 5 SECOND for sticky bump
			$query = "UPDATE {$this->threadTable}
					  SET last_bump_time = NOW() + INTERVAL 5 SECOND
					  WHERE thread_uid = :thread_uid";
	
			$params = [
				':thread_uid' => $threadID
			];
		} else {
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
		}
	
		$this->databaseConnection->execute($query, $params);
	}

	public function getThreadsFromBoard(int $boardUid, int $limit = 50, int $offset = 0, string $orderBy = 'last_bump_time', string $direction = 'DESC'): array|null {
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
			FROM {$this->threadTable}
			WHERE boardUID = :board_uid
			ORDER BY $orderBy {$direction}
		";

		if($limit) {
			$query .= "LIMIT {$limit} OFFSET {$offset}";
		}

		// Bind parameters to prevent SQL injection
		$params = [':board_uid' => $boardUid];

		// Execute the query and return results as an array
		$threads = $this->databaseConnection->fetchAllAsArray($query, $params);

		return $threads;
	}


	public function getPostsFromThread(string $threadUID): array|null {
		$query = "SELECT * FROM {$this->postTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $threadUID
		];
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
		
		//get posts from parent thread
		if(!$posts) {
			$query = "SELECT * FROM {$this->postTable} 
								WHERE thread_uid = (
								SELECT thread_uid 
								FROM {$this->postTable} 
								WHERE post_uid = :post_uid
								)";
			$params = [':post_uid' => $threadUID]; // Rename to avoid confusion
			$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
		}
		
		return $posts ?? false;
	}

	public function getPostsForThreads(array $threadUIDs): array {
		if (empty($threadUIDs)) return [];

		$inClause = implode(',', array_fill(0, count($threadUIDs), '?'));

		$query = "
			SELECT *
			FROM {$this->postTable}
			WHERE thread_uid IN ($inClause)
			ORDER BY no ASC
		";

		return $this->databaseConnection->fetchAllAsArray($query, $threadUIDs);
	}

	public function getAllAttachmentsFromThread($thread_uid) {
		$query = "SELECT ext, tim, boardUID FROM {$this->postTable} WHERE thread_uid = :thread_uid";
		$params[':thread_uid'] = $thread_uid;

		$threadAttachments = $this->databaseConnection->fetchAllAsArray($query, $params);
		return $threadAttachments;
	}

	/* Get number of discussion threads */
	public function threadCountFromBoard($board) {
		$query = "SELECT COUNT(thread_uid) FROM {$this->threadTable} WHERE boardUID = :board_uid";
		return $this->databaseConnection->fetchColumn($query, [':board_uid' => $board->getBoardUID()]);
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

}