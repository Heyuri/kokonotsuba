<?php

// singleton for managing threads

class threadSingleton {
	private $threadTable, $postTable;
	private $databaseConnection; // Database connection
	private static $instance;
	private array $allowedOrderFields;

	public function __construct($dbSettings){
		$this->threadTable = $dbSettings['THREAD_TABLE'];
		$this->postTable = $dbSettings['POST_TABLE'];
		
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
	
		$this->allowedOrderFields = ['last_bump_time', 'last_reply_time', 'thread_created_time'];
	}
	
	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			$globalConfig = getGlobalConfig();
			self::$instance = new LoggerInjector(
				new self($dbSettings),
				new LoggerInterceptor(PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'], 'PIOPDO')));
		}
		return self::$instance;
	}
	
	public static function getInstance() {
		return self::$instance;
	}

		/* Transactions methods */
	public function beginTransaction() {
		$this->databaseConnection->beginTransaction();
	}

	public function commit() {
		$this->databaseConnection->commit();
	}

	public function rollBack() {
		$this->databaseConnection->rollBack();
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

	public function getThreadByUID($thread_uid) {
		$query = "SELECT * FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => (string) $thread_uid
		];
	
		return $this->databaseConnection->fetchOne($query, $params);
	}
	

	/**
 	* Fetch thread UIDs for a given board with optional sorting and pagination.
 	*
	*/
	public function getThreadListFromBoard(
		board $board,
		int $start = 0,
		int $amount = 0,
		bool $isDESC = true,
		string $orderBy = 'last_bump_time'
	): array {
		// Validate orderBy to prevent SQL injection
		if (!in_array($orderBy, $this->allowedOrderFields, true)) {
				$orderBy = 'last_bump_time';
		}

		$direction = $isDESC ? 'DESC' : 'ASC';

		$query = "SELECT thread_uid FROM {$this->threadTable}
		          WHERE boardUID = :board_uid
		          ORDER BY {$orderBy} {$direction}";

		// Append LIMIT clause directly (safe because it's an int)
		if ($amount > 0) {
				$start  = max(0, (int)$start);
				$amount = max(1, (int)$amount);
				$query .= " LIMIT {$start}, {$amount}";
		}

		$params = [':board_uid' => $board->getBoardUID()];

		$threads = $this->databaseConnection->fetchAllAsIndexArray($query, $params);

		return !empty($threads) ? array_merge(...$threads) : [];
	}

	public function mapThreadUidListToPostNumber($threadUidArray) {
		if (!is_array($threadUidArray)) {
			$threadUidArray = [$threadUidArray];
		}
	
		addSlashesToArray($threadUidArray);
	
		$threadUidArray = implode(',', $threadUidArray);
		$query = "SELECT post_op_number, boardUID FROM {$this->threadTable} WHERE thread_uid IN ({$threadUidArray}) ORDER BY last_bump_time DESC";
		
		return $this->databaseConnection->fetchAllAsArray($query);
	
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

	public function getLastInsertedThreadUID() {
		$query = "SELECT MAX(thread_uid) FROM {$this->threadTable}";
		$result = $this->databaseConnection->fetchColumn($query);
		return $result;
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
		$posts = $this->fetchPostsFromThread($threadID);
		if (empty($posts)) return;

		$lastPost = end($posts);
		$bumpTime = $lastPost['root'];

		if ($sticky) {
				$bumpTimestamp = time() + 5; // add 5 seconds
				$bumpTime = date('Y-m-d H:i:s', $bumpTimestamp);
		}

		$query = "UPDATE {$this->threadTable}
				  SET last_bump_time = :bump_time,
					  last_reply_time = :bump_time
				  WHERE thread_uid = :thread_uid";

		$params = [
				':bump_time'   => $bumpTime,
				':thread_uid'  => $threadID
		];

		$this->databaseConnection->execute($query, $params);
	}


	public function updateThreadLastReplyTime($threadID) {
		$query = "UPDATE {$this->threadTable} SET last_reply_time = CURRENT_TIMESTAMP WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $threadID
		]; 
	
		$this->databaseConnection->execute($query, $params);
	}

	public function getPostCountFromThread($threadUID) {
		if(!$threadUID) throw new Exception("Invalid thread UID in ".__METHOD__);
		$query = "SELECT COUNT(post_uid) FROM {$this->postTable} WHERE thread_uid = ?";
		$count = $this->databaseConnection->fetchColumn($query, [$threadUID]);
		return $count;
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

		/* Get number of discussion threads */
	public function threadCountFromBoard($board) {
		$query = "SELECT COUNT(thread_uid) FROM {$this->threadTable} WHERE boardUID = :board_uid";
		return $this->databaseConnection->fetchColumn($query, [':board_uid' => $board->getBoardUID()]);
	}

	public function fetchPostsFromThread($threadUID) {
		$threadUID = strval($threadUID);
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

	/* Output discussion thread list */
	public function fetchThreadListFromBoard(board $board, int $start = 0, int $amount = 0, bool $isDESC = false): array {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE boardUID = :board_uid ORDER BY last_bump_time";
		if ($isDESC) {
				$query .= " DESC";
		}
		if ($amount) {
				$query .= " LIMIT {$start}, {$amount}";
		}

		return array_merge(...$this->databaseConnection->fetchAllAsIndexArray($query, [':board_uid' => $board->getBoardUID()])) ?? [];
	}

	public function getFilteredThreads(int $amount, int $offset = 0, array $filters = [], string $order = 'last_bump_time'): array {
		$query = "SELECT * FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$threads = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		return $threads ?? [];
	}
	
	public function getFilteredThreadUIDs(int $amount, int $offset = 0, array $filters = [], string $order = 'last_bump_time') {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$threads = $this->databaseConnection->fetchAllAsIndexArray($query, $params);
		return array_merge(...$threads);
	}

	public function getFilteredThreadCount($filters = []) {
		$query = "SELECT COUNT(thread_uid) FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$threads = $this->databaseConnection->fetchColumn($query, $params);
	
		return $threads;
	}

	public function isThread($threadID) {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => strval($threadID)
		];
		$threadExists = $this->databaseConnection->fetchColumn($query, $params) ? true : false;
		return $threadExists;
	}

	public function getAllAttachmentsFromThread($thread_uid) {
		$query = "SELECT ext, tim, boardUID FROM {$this->postTable} WHERE thread_uid = :thread_uid";
		$params[':thread_uid'] = $thread_uid;

		$threadAttachments = $this->databaseConnection->fetchAllAsArray($query, $params);
		return $threadAttachments;
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

	public function getLastThreadTimeFromBoard($board) {
		$boardUID = $board->getBoardUID();
		
		$query = "SELECT MAX(thread_created_time) FROM {$this->threadTable} WHERE boardUID = :boardUID";
		$params = [
			':boardUID' => $boardUID,
		];
		$lastThreadTime = $this->databaseConnection->fetchColumn($query, $params);
		return $lastThreadTime;
	}

	public function moveThreadAndUpdate($thread_uid, $sourceBoard, $destinationBoard) {
		$this->beginTransaction();
		try {
			$posts = $this->fetchPostsFromThread($thread_uid);
	
			if (empty($posts)) {
				throw new Exception("No posts found for thread UID: $thread_uid");
			}
	
			// Get the last post number on the destination board
			$lastPostNumber = $destinationBoard->getLastPostNoFromBoard();
	
			// Mapping for old to new post numbers
			$postNumberMapping = [];
			$newThreadPostNumber = -1;

			// Update each post
			foreach ($posts as $key=>$post) {
				$oldPostNumber = $post['no'];
				$newPostNumber = ++$lastPostNumber;

	
				// Map old to new post numbers
				$postNumberMapping[$oldPostNumber] = $newPostNumber;
				// Update the post content (com) to update quote links
				$updatedCom = preg_replace_callback('/&gt;&gt;([0-9]+)/', function ($matches) use ($postNumberMapping) {
					$oldQuote = $matches[1];
					return isset($postNumberMapping[$oldQuote]) ? '&gt;&gt;' . $postNumberMapping[$oldQuote] : $matches[0];
				}, $post['com']);
	
				// Execute the update query
				$updatePostQuery = "UPDATE {$this->postTable} 
							SET no = :new_no, boardUID = :new_boardUID, com = :updated_com 
							WHERE post_uid = :post_uid";
				$updateParams = [
					':new_no' => intval($newPostNumber),
					':new_boardUID' => intval($destinationBoard->getBoardUID()),
					':updated_com' => strval($updatedCom),
					':post_uid' => strval($post['post_uid']),
				];
				$this->databaseConnection->execute($updatePostQuery, $updateParams);
				$destinationBoard->incrementBoardPostNumber();

				//op post
				if($key === 0) $newThreadPostNumber = $newPostNumber;
			}
	
			// Update the thread's board UID
			$updateThreadQuery = "UPDATE {$this->threadTable} 
								SET boardUID = :new_boardUID, post_op_number = :new_post_op_number
								WHERE thread_uid = :thread_uid";
			$updateThreadParams = [
				':new_boardUID' => intval($destinationBoard->getBoardUID()),
				':thread_uid' => strval($thread_uid),
				':new_post_op_number' => intval($newThreadPostNumber),
			];
			$this->databaseConnection->execute($updateThreadQuery, $updateThreadParams);
			$this->commit();
		} catch (Exception $e) {
			$this->rollBack();
			throw $e;
		}
	}

	public function resolveThreadNumberFromUID($thread_uid) {
		$query = "SELECT post_op_number FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => strval($thread_uid)
		];
		$threadNo = $this->databaseConnection->fetchColumn($query, $params);
		return $threadNo;
	}

		/* Output article */
	public function fetchThreads($postlist, $fields = '*') {
		if (!is_array($postlist)) {
			$postlist = [$postlist];
		}
	
		addSlashesToArray($postlist);
	
		$postlist = implode(',', $postlist);
		$query = "SELECT {$fields} FROM {$this->threadTable} WHERE thread_uid IN ({$postlist})";
		
		return $this->databaseConnection->fetchAllAsArray($query);
	}

	public function copyThreadAndPosts($originalThreadUid, $destinationBoard) {
		$this->beginTransaction();
	
		try {
			$posts = $this->fetchPostsFromThread($originalThreadUid);
			$this->validatePostsExist($posts, $originalThreadUid);
	
			$newThreadUid = generateUid();
			$boardUID = $destinationBoard->getBoardUID();
			$lastPostNo = $destinationBoard->getLastPostNoFromBoard();
			$postNumberMapping = [];
			$newPostsData = [];
	
			// Get new OP post number
			$newOpPostNumber = $lastPostNo + 1;
	
			// Insert thread first (with NULL for post_op_post_uid for now)
			$this->insertThreadRecord($newThreadUid, $newOpPostNumber, $boardUID);
	
			// Map post data and generate new post numbers
			foreach ($posts as $post) {
				$newPostNumber = ++$lastPostNo;
				$postNumberMapping[$post['no']] = $newPostNumber;
				$destinationBoard->incrementBoardPostNumber();
	
				$newPost = $this->mapPostData($post, $boardUID, $newPostNumber, $newThreadUid);
				$newPostsData[] = $newPost;
			}
	
			// Update quote references
			foreach ($newPostsData as &$postData) {
				$postData['com'] = $this->updateQuoteReferences($postData['com'], $postNumberMapping);
			}
	
			// Insert posts and get OP post UID
			$opPostUid = -1;
			foreach ($newPostsData as $i => $postData) {
				$columns = implode(', ', array_map(fn($k) => "`$k`", array_keys($postData)));
				$placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($postData)));
				$query = "INSERT INTO {$this->postTable} ($columns) VALUES ($placeholders)";
				$this->databaseConnection->execute($query, array_combine(
					array_map(fn($k) => ":$k", array_keys($postData)),
					array_values($postData)
				));
	
				if ($i === 0) {
					$opPostUid = $this->databaseConnection->lastInsertId();
				}
			}
	
			// Update thread record with real OP post UID
			$this->updateThreadOpPostUid($newThreadUid, $opPostUid);
	
			$this->commit();
			return $newThreadUid;
		} catch (Exception $e) {
			$this->rollBack();
			throw $e;
		}
	}
	

	private function validatePostsExist($posts, $originalThreadUid) {
		if (empty($posts)) {
			throw new Exception("No posts found for thread UID: $originalThreadUid");
		}
	}
	
	private function insertThreadRecord($threadUid, $postOpNumber, $boardUID) {
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
	
	private function updateThreadOpPostUid($threadUid, $postUid) {
		$this->databaseConnection->execute(
			"UPDATE {$this->threadTable}
			 SET post_op_post_uid = :post_uid
			 WHERE thread_uid = :thread_uid",
			[
				':post_uid'		=> $postUid,
				':thread_uid'	=> $threadUid
			]
		);
	}
	
	private function mapPostData($post, $boardUID, $newPostNumber, $newThreadUid) {
		return [
			'no'			=> $newPostNumber,
			'boardUID'		=> $boardUID,
			'thread_uid'	=> $newThreadUid,
			'root'			=> $post['root'],
			'time'			=> $post['time'],
			'md5chksum'		=> $post['md5chksum'],
			'category'		=> $post['category'],
			'tim'			=> $post['tim'],
			'fname'			=> $post['fname'],
			'ext'			=> $post['ext'],
			'imgw'			=> $post['imgw'],
			'imgh'			=> $post['imgh'],
			'imgsize'		=> $post['imgsize'],
			'tw'			=> $post['tw'],
			'th'			=> $post['th'],
			'pwd'			=> $post['pwd'],
			'now'			=> $post['now'],
			'name'			=> $post['name'],
			'email'			=> $post['email'],
			'sub'			=> $post['sub'],
			'com'			=> $post['com'],
			'host'			=> $post['host'],
			'status'		=> $post['status']
		];
	}
	
	private function updateQuoteReferences($comment, $postNumberMapping) {
		return preg_replace_callback('/&gt;&gt;(\d+)/', function ($matches) use ($postNumberMapping) {
			$oldQuote = $matches[1];
			return isset($postNumberMapping[$oldQuote]) ? '&gt;&gt;' . $postNumberMapping[$oldQuote] : $matches[0];
		}, $comment);
	}
	

}