<?php
/**
 * PDO post API 
 *
 * @package PMCLibrary
 */

class PIOPDO implements IPIO {
	private $threadTable, $tablename, $loadedBoards; // Table name
	private $databaseConnection; // Database connection
	private static $instance;

	public function __construct($dbSettings){
		$boardIO = boardIO::getInstance();
		
		$this->tablename = $dbSettings['POST_TABLE'];
		$this->threadTable = $dbSettings['THREAD_TABLE'];
		
		$this->loadedBoards = $boardIO->getAllBoards();
		
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
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

	/* PIO module version */
	public function pioVersion() {
		return '1.0 (PDO Input/Output for posts)';
	}

	// set the parameters and handle filters for the filter query
	private function bindfiltersParameters(&$params, &$query, $filters) {
		// validate and sanitize 'board' filter
		if (isset($filters['board']) && is_array($filters['board'])) {
			$filters['board'] = array_filter($filters['board'], function ($value) {
				return is_string($value);
			});
		}		

		// apply 'board' filter
		if (!empty($filters['board'])) {
			$query .= " AND (";
			foreach ($filters['board'] as $index => $board) {
				$query .= ($index > 0 ? " OR " : "") . "boardUID = :board_$index";
				$params[":board_$index"] = (int)$board;
			}
			$query .= ")";
		}

		// sanitize text filters
		if (isset($filters['comment']) && is_string($filters['comment'])) {
			$query .= " AND com LIKE :comment";
			$params[':comment'] = '%' . $filters['comment'] . '%';
		}

		if (isset($filters['subject']) && is_string($filters['subject'])) {
			$query .= " AND sub LIKE :subject";
			$params[':subject'] = '%' . $filters['subject'] . '%';
		}

		if (isset($filters['name']) && is_string($filters['name'])) {
			$query .= " AND name LIKE :name";
			$params[':name'] = '%' . $filters['name'] . '%';
		}

		// validate and convert ip_address filter to regex safely
		if (isset($filters['ip_address']) && is_string($filters['ip_address'])) {
			// allow only digits, dots, and asterisks
			if (preg_match('/^[\d\.\*]+$/', $filters['ip_address'])) {
				$ip_pattern = preg_quote($filters['ip_address'], '/');
				$ip_pattern = str_replace('\*', '.*', $ip_pattern);
				$ip_regex = "^$ip_pattern$";

				$query .= " AND host REGEXP :ip_regex";
				$params[':ip_regex'] = $ip_regex;
			}
		}
	}

	
	public function getAllPosts() {
		$query = "SELECT * FROM {$this->tablename} ORDER BY post_uid DESC";
		$posts = $this->databaseConnection->fetchAllAsArray($query);
		return $posts;
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
		$params[':thread_uid'] = strval($thread_uid);
		return $this->databaseConnection->fetchOne($query, $params);
	}

	/* Fetch the list of threads */
	public function getThreadListFromBoard(board $board, int $start = 0, int $amount = 0, bool $isDESC = true, string $orderBy = 'last_bump_time'): array {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE boardUID = :board_uid ORDER BY $orderBy " . ($isDESC ? "DESC" : "ASC");

		$params = [
			':board_uid' => $board->getBoardUID(),
		];

		if ($amount > 0) {
			$query .= " LIMIT $start, $amount";
		}
		$threads = $this->databaseConnection->fetchAllAsIndexArray($query, $params);
		return array_merge(...$threads) ?? [];
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
	
	/* Check if the post is successive (rate limiting mechanism) */
	public function isSuccessivePost($board, $lcount, $com, $timestamp, $pass, $passcookie, $host, $isupload) {
		$config = $board->loadBoardConfig();
		
		$timeCheckSQL = "SELECT pwd, host FROM {$this->tablename} WHERE time > ?";
		$timeLimit = $timestamp - $config['RENZOKU']; // Time window to check
		$params = [$timeLimit];

		if ($isupload) {
			$timeLimitUpload = $timestamp - $config['RENZOKU2'];
			$timeCheckSQL .= ' OR (fname != "" AND time > ?)';
			$params[] = $timeLimitUpload;
		}

		$results = $this->databaseConnection->fetchAllAsArray($timeCheckSQL, $params);

		foreach ($results as $result) {
			if ($host === $result['host'] || $pass === $result['pwd'] || $passcookie === $result['pwd']) {
				return true; // Post is successive
			}
		}

		return false; // Not a successive post
	}

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
	
	/* Add a new post to a thread */
	public function addPost($board, $no, $thread_uid_from_url, $md5chksum, $category, $tim, $fname, $ext, $imgw, $imgh, 
		$imgsize, $tw, $th, $pwd, $now, $name, $email, $sub, $com, $host,  $age = false, $status = '') {
		
		$this->beginTransaction();
		try {
			$boardUID = $board->getBoardUID();
			$time = (int)substr($tim, 0, -3);
			$root = gmdate('Y-m-d H:i:s');
			$postUID = $this->databaseConnection->getNextAutoIncrement($this->tablename);
			$thread_uid_for_database = null;
			$isThread = false;
			
			$board->incrementBoardPostNumber();
			
			if(!$thread_uid_from_url) {
				//create a new thread
				$thread_uid = generateUid();
				$this->addThread($boardUID, $postUID, $thread_uid, $no);
				$thread_uid_for_database = $thread_uid;
				$isThread = true;
			} else {
				//post to an existing thread
				$thread_uid_for_database = $thread_uid_from_url;
			}
			
			$query = "INSERT INTO {$this->tablename} 
				(no, boardUID, thread_uid, root, time, md5chksum, 
				category, tim, fname, ext, imgw, imgh, imgsize, tw, th, pwd, now, 
				name, email, sub, com, host, status) 
				VALUES (:no, :boardUID, :thread_uid, :root, :time,
				:md5chksum, :category, :tim, :fname, :ext, :imgw, :imgh, :imgsize, :tw, :th, 
				:pwd, :now, :name, :email, :sub, :com, :host, :status)";
		
			$params = [
				':no'          => $no,
				':boardUID'    => $boardUID,
				':thread_uid'  => $thread_uid_for_database,
				':root'        => $root,
				':time'        => $time,
				':md5chksum'   => $md5chksum,
				':category'    => $category,
				':tim'         => $tim,
				':fname'       => $fname,
				':ext'         => $ext,
				':imgw'        => $imgw,
				':imgh'        => $imgh,
				':imgsize'     => $imgsize,
				':tw'          => $tw,
				':th'          => $th,
				':pwd'         => $pwd,
				':now'         => $now,
				':name'        => $name,
				':email'       => $email,
				':sub'         => $sub,
				':com'         => $com,
				':host'        => $host,
				':status'      => $status
			];

			$this->databaseConnection->execute($query, $params);

			if ($age || $isThread) $this->bumpThread($thread_uid_for_database);
			else $this->updateThreadLastReplyTime($thread_uid_for_database);
			$this->commit();
		} catch (Exception $e) {
			$this->rollBack();
			throw $e;
		}
	}

	public function getLastInsertedThreadUID() {
		$query = "SELECT MAX(thread_uid) FROM {$this->threadTable}";
		$result = $this->databaseConnection->fetchColumn($query);
		return $result;
	}

	public function getThreadOpPostsFromList($threadUIDList) {
		if (!is_array($threadUIDList)) {
			$threadUIDList = [$threadUIDList];
		}
	
		addSlashesToArray($threadUIDList);
	
		$threadUIDList = implode(',', $threadUIDList);
		$query = "SELECT p.* FROM {$this->tablename} p
			INNER JOIN (
				SELECT thread_uid, MIN(post_uid) AS min_post_uid
				FROM {$this->tablename}
				WHERE thread_uid IN ($threadUIDList)
				GROUP BY thread_uid) first_posts ON p.thread_uid = first_posts.thread_uid AND p.post_uid = first_posts.min_post_uid
				ORDER BY post_uid DESC;";

		return $this->databaseConnection->fetchAllAsArray($query);
	}

	/* Bump a discussion thread */
	public function bumpThread($threadID, $future = false) {
		$postsFromThread = $this->fetchPostsFromThread($threadID);
		if(empty($postsFromThread)) return;
		
		$lastReply = end($postsFromThread);
		
		$lastReplyRegistTime = $lastReply['root'];
		
		if ($future) {
			$timestamp = $lastReply['time'];
			$secondsToAdd = 5;
			$futureTimestamp = $timestamp + $secondsToAdd;
			$lastReplyRegistTime = date('Y-m-d H:i:s', $futureTimestamp);
		}
		
		$query = "UPDATE {$this->threadTable} SET last_bump_time = :post_regist_time, last_reply_time = :post_regist_time WHERE thread_uid = :thread_uid";
		$params = [
		':post_regist_time' => $lastReplyRegistTime,
		':thread_uid' => $threadID];

		$this->databaseConnection->execute($query, $params);
	}

	public function updateThreadLastReplyTime($threadID) {
		$query = "UPDATE {$this->threadTable} SET last_reply_time = CURRENT_TIMESTAMP WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $threadID
		]; 
	
		$this->databaseConnection->execute($query, $params);
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

	/* Search posts */
	public function searchPost($board, $keywords, $field = 'com', $method = 'OR') {
		// Validate the field and method inputs
		$allowedFields = ['com', 'name', 'sub', 'no'];
		$field = in_array($field, $allowedFields) ? $field : 'com';
		$method = in_array($method, ['AND', 'OR']) ? $method : 'OR';
		$boardUID = $board->getBoardUID();

		$whereClauses = [];
		$params = [];
		foreach ($keywords as $keyword) {
			$whereClauses[] = "LOWER($field) LIKE :keyword";
			$params[':keyword'] = '%' . strtolower($keyword) . '%';
		}
		$whereClause = implode(" $method ", $whereClauses);

		$params[':board_uid'] = $boardUID;

		$query = "SELECT * FROM {$this->tablename} WHERE $whereClause AND boardUID = :board_uid ORDER BY no DESC";
		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	/* Check if an attachment is duplicated */
	public function isDuplicateAttachment($board, $lcount, $md5hash) {
		$query = "SELECT tim, ext FROM {$this->tablename} WHERE ext <> '' AND md5chksum = :md5chksum AND boardUID = :boardUID ORDER BY no DESC";
		$params = [
			':md5chksum' => $md5hash,
			':boardUID' => $board->getBoardUID(),
		];
		$results = $this->databaseConnection->fetchAllAsArray($query, $params);

		$FileIO = PMCLibrary::getFileIOInstance();
		foreach ($results as $row) {
			$filename = $row['tim'] . $row['ext'];
			if ($FileIO->imageExists($filename, $board)) {
				return true; // Duplicate found
			}
		}
		return false; // No duplicate
	}

	/* Search posts by category */
	public function searchCategory($category) {
		// Prepare the query to search for posts that have the category
		$query = "SELECT post_uid FROM {$this->tablename} WHERE boardUID = :board_uid AND LOWER(category) LIKE :expression";
		
		$params[':expression'] = ['%' . strtolower($category) . '%'];

		$foundPosts = $this->databaseConnection->fetchAllAsArray($query, $params);
		return array_column($foundPosts, 'no'); // Return post numbers
	}

	/* Get the status of a post */
	public function getPostStatus($post_uid) {
		$query = "SELECT status FROM {$this->tablename} WHERE post_uid = ?";
		$status = $this->databaseConnection->fetchColumn($query, [$post_uid]);
		return new FlagHelper($status !== false ? $status : null);
	}

	/* Set the status of a post */
	public function setPostStatus($post_uid, $newStatus) {
		$query = "UPDATE {$this->tablename} SET status = ? WHERE post_uid = ?";
		$params = [$newStatus, strval($post_uid)];
		$this->databaseConnection->execute($query, $params);
			
		return true;
	}
	
	public function getPostIP($no) {
	    $query = "SELECT host FROM {$this->tablename} WHERE no = ?";
	    $ip = $this->databaseConnection->fetchColumn($query, [intval($no)]);
    
	    return $ip !== false ? $ip : null;
	}

	public function getPostCountFromThread($threadUID) {
		if(!$threadUID) throw new Exception("Invalid thread UID in ".__METHOD__);
		$query = "SELECT COUNT(post_uid) FROM {$this->tablename} WHERE thread_uid = ?";
		$count = $this->databaseConnection->fetchColumn($query, [$threadUID]);
		return $count;
	}
	
	/* Get number of posts */
	public function postCountFromBoard($board, $threadUID = 0) {
		if ($threadUID) {
			$query = "SELECT COUNT(post_uid) FROM {$this->tablename} WHERE thread_uid = ?";
			$count = $this->databaseConnection->fetchColumn($query, [$threadUID]);
			return $count + 1;
		} else {
			$query = "SELECT COUNT(post_uid) FROM {$this->tablename} WHERE boardUID = :board_uid";
			return $this->databaseConnection->fetchColumn($query, [':board_uid' => $board->getBoardUID()]);
		}
		return 0;
	}
		/* Get number of posts */
	public function postCount($filters = []) {
		$query = "SELECT COUNT(post_uid) FROM {$this->tablename} WHERE 1 ";
		$params = [];
		$this->bindfiltersParameters($params, $query, $filters);
		
		return $this->databaseConnection->fetchColumn($query, $params);
	}

	/* Get number of discussion threads */
	public function threadCountFromBoard($board) {
		$query = "SELECT COUNT(thread_uid) FROM {$this->threadTable} WHERE boardUID = :board_uid";
		return $this->databaseConnection->fetchColumn($query, [':board_uid' => $board->getBoardUID()]);
	}

	public function fetchPostsFromThread($threadUID, $start = 0, $amount = 0) {
		$threadUID = strval($threadUID);
		$query = "SELECT * FROM {$this->tablename} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $threadUID
		];
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
		
		//get posts from parent thread
		if(!$posts) {
			$query = "SELECT * FROM {$this->tablename} 
								WHERE thread_uid = (
								SELECT thread_uid 
								FROM {$this->tablename} 
								WHERE post_uid = :post_uid
								)";
			$params = [':post_uid' => $threadUID]; // Rename to avoid confusion
			$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
		}
		
		return $posts ?? false;
	}

	/* Output list of articles */
	public function fetchPostList(mixed $resno = 0, int $start = 0, int $amount = 0, string $host = ''): array {
		$resno = strval($resno);

		if ($resno) {
				$query = "SELECT post_uid FROM {$this->tablename} WHERE `thread_uid` = :resno ORDER BY no";
				$posts = $this->databaseConnection->fetchAllAsArray($query, [':resno' => $resno]);
		} else {
				$query = "SELECT post_uid FROM {$this->tablename}" . ($host ? " WHERE `host` = :host" : "") . " ORDER BY no DESC";
				$params = $host ? [':host' => $host] : [];
				if ($amount) {
						$query .= " LIMIT {$start}, {$amount}";
				}
				$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
		}
		return array_column($posts, 'post_uid') ?? [];
	}

	/* Output list of articles */
	public function fetchPostListFromBoard(board $board, mixed $resno = 0, int $start = 0, int $amount = 0, string $host = ''): array {
		$resno = strval($resno);

		if ($resno) {
				$query = "SELECT post_uid FROM {$this->tablename} WHERE `thread_uid` = :thread_uid AND boardUID = :board_uid ORDER BY no";
				$posts = $this->databaseConnection->fetchAllAsArray($query, [':thread_uid' => $resno, ':board_uid' => $board->getBoardUID()]);
		} else {
				$query = "SELECT post_uid FROM {$this->tablename} WHERE `boardUID` = :board_uid" . ($host ? " AND `host` = :host" : "") . " ORDER BY no DESC";
				$params = $host ? [':host' => $host, ':board_uid' => $board->getBoardUID()] : [':board_uid' => $board->getBoardUID()];
				if ($amount) {
						$query .= " LIMIT {$start}, {$amount}";
				}
				$posts = $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];
		}
		return array_column($posts, 'post_uid') ?? [];
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

	public function getPostsFromBoard(board $board, int $start = 0, int $amount = 0, string $order = "no", string $sortOrder = "DESC"): array {
		$query = "SELECT * FROM {$this->tablename} WHERE boardUID = :board_uid ORDER BY $order $sortOrder";
		if($amount) {
			$query .= " LIMIT $start, $amount";
		}
		$params[':board_uid'] = intval($board->getBoardUID());
		return $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];
	}
	
	public function getPostsFromIP(string $host, string $order = "post_uid"): array {
		$query = "SELECT * FROM {$this->tablename} WHERE host = :ip_address ORDER BY $order";
		$params = [':ip_address' => $host];
		
		return $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];
	}
	
	public function getFilteredPosts(int $amount, int $offset = 0, array $filters = [], string $order = 'post_uid'): array {
		$query = "SELECT * FROM {$this->tablename} WHERE 1";
		$params = [];
		
		$this->bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		return $posts ?? [];
	}
	
	public function getFilteredThreads(int $amount, int $offset = 0, array $filters = [], string $order = 'last_bump_time'): array {
		$query = "SELECT * FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		$this->bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$threads = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		return $threads ?? [];
	}
	
	public function getFilteredThreadUIDs(int $amount, int $offset = 0, array $filters = [], string $order = 'last_bump_time') {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		$this->bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$threads = $this->databaseConnection->fetchAllAsIndexArray($query, $params);
		return array_merge(...$threads);
	}
	
	public function getFilteredThreadCount($filters = []) {
		$query = "SELECT COUNT(thread_uid) FROM {$this->threadTable} WHERE 1";
		$params = [];
		
		$this->bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$threads = $this->databaseConnection->fetchColumn($query, $params);
	
		return $threads;
	}
	
	/* Output article */
	public function fetchPosts($postlist, $fields = '*') {
		if (!is_array($postlist)) {
			$postlist = [$postlist];
		}
	
		addSlashesToArray($postlist);
	
		$postlist = implode(',', $postlist);
		$query = "SELECT {$fields} FROM {$this->tablename} WHERE post_uid IN ({$postlist}) OR thread_uid IN ({$postlist})";
		
		return $this->databaseConnection->fetchAllAsArray($query);
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

	public function getPostOpUIDsFromThreadList($threadList) {
		if (!is_array($threadList)) {
			$threadList = [$threadList];
		}
	
		addSlashesToArray($threadList);
	
		$postlist = implode(',', $threadList);
		$query = "SELECT post_op_post_uid FROM {$this->threadTable} WHERE thread_uid IN ({$postlist})";
		
		return array_merge(...$this->databaseConnection->fetchAllAsIndexArray($query));
	}
	


	/* Update post */
	public function updatePost($post_uid, $newValues) {
		$setClause = [];
		$params = [];
		foreach ($newValues as $field => $value) {
			$setClause[] = "$field = ?";
			$params[] = $value;
		}
		$params[] = strval($post_uid);
		$query = "UPDATE {$this->tablename} SET " . implode(', ', $setClause) . " WHERE post_uid = ?";
		$this->databaseConnection->execute($query, $params);
	}

	/* Check whether a post exists */
	public function postExists($post_uid) {
		$query = "SELECT post_uid FROM {$this->tablename} WHERE post_uid = ?";
		return $this->databaseConnection->fetchColumn($query, [$post_uid]) ? true : false;
	}

	/* Delete old attachments */
	public function delOldAttachments($board, $total_size, $storage_max, $warnOnly = true) {
		$FileIO = PMCLibrary::getFileIOInstance();
		$query = "SELECT post_uid, ext, tim FROM {$this->tablename} WHERE ext <> '' ORDER BY no";
		$results = $this->databaseConnection->fetchAllAsArray($query);

		$arr_warn = [];
		$arr_kill = [];

		foreach ($results as $row) {
			$dfile = $row['tim'] . $row['ext'];
			$dthumb = $FileIO->resolveThumbName($row['tim'], $board);

			if ($FileIO->imageExists($dfile, $board)) {
				$total_size -= $FileIO->getImageFilesize($dfile, $board) / 1024;
				$arr_kill[] = $row['no'];
				$arr_warn[$row['no']] = 1;
			}
			if ($dthumb && $FileIO->imageExists($dthumb, $board)) {
				$total_size -= $FileIO->getImageFilesize($dthumb, $board) / 1024;
			}

			if ($total_size < $storage_max) break;
		}

		return $warnOnly ? $arr_warn : $this->removeAttachments($arr_kill);
	}

	/* Delete post */
	public function removePosts($posts) {
		if (count($posts) == 0) return [];
		if (!is_array($posts)) {
			$posts = [$posts];
		}
		
		$this->databaseConnection->beginTransaction();
		try {
			$files = $this->removeAttachments($posts, true);

			addSlashesToArray($posts);
			$postUIDsList = implode(', ', $posts);

			$threadUIDs = $this->databaseConnection->fetchColumn("
				SELECT DISTINCT thread_uid
				FROM {$this->tablename}
				WHERE post_uid IN ({$postUIDsList})
			");

			$this->databaseConnection->execute("DELETE FROM {$this->tablename} WHERE post_uid IN ({$postUIDsList})");
			$this->databaseConnection->execute("DELETE FROM {$this->threadTable} WHERE post_op_post_uid IN ({$postUIDsList})");
			$this->databaseConnection->execute("DELETE FROM {$this->threadTable} WHERE thread_uid IN ({$postUIDsList})");

			if(!is_array($threadUIDs)) $threadUIDs = [$threadUIDs];
			foreach ($threadUIDs as $threadUID) {
				$newReplyData = $this->databaseConnection->fetchOne("
					SELECT `root`
					FROM {$this->tablename}
					WHERE thread_uid = ?
					ORDER BY `root` DESC
					LIMIT 1
				", [$threadUID]);

				if ($newReplyData === null) {
					$this->databaseConnection->execute("
						DELETE FROM {$this->threadTable}
						WHERE thread_uid = ?
					", [$threadUID]);
				} else {
					$this->databaseConnection->execute("
						UPDATE {$this->threadTable}
						SET last_bump_time = ?, last_reply_time = ?
						WHERE thread_uid = ?
					", [$newReplyData['root'], $newReplyData['root'], $threadUID]);
				}
			}

			$this->databaseConnection->commit();
			return $files;
		} catch (Exception $e) {
			$this->databaseConnection->rollBack();
			throw $e;
		}
	}

	public function isThread($threadID) {
		$query = "SELECT thread_uid FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => strval($threadID)
		];
		$threadExists = $this->databaseConnection->fetchColumn($query, $params) ? true : false;
		return $threadExists;
	}
	
	public function isThreadOP($post_uid) {
		$query = "SELECT post_op_post_uid FROM {$this->threadTable} WHERE post_op_post_uid = :post_op_post_uid";
		$params = [
			':post_op_post_uid' => strval($post_uid)
		];
		$threadExists = $this->databaseConnection->fetchColumn($query, $params) ? true : false;
		return $threadExists;
	}
	
	public function getAllAttachmentsFromThread($thread_uid) {
		$query = "SELECT ext, tim, boardUID FROM {$this->tablename} WHERE thread_uid = :thread_uid";
		$params[':thread_uid'] = $thread_uid;

		$threadAttachments = $this->databaseConnection->fetchAllAsArray($query, $params);
		return $threadAttachments;
	}

	public function updatePostBoardUIDsFromThread($thread_uid, $destinationBoard) {
		$query = "UPDATE {$this->tablename} SET boardUID = :board_uid WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $thread_uid,
			':board_uid' => $destinationBoard->getBoardUID()
		];
		$this->databaseConnection->execute($query, $params);

		$query = "UPDATE {$this->threadTable} SET boardUID = :board_uid WHERE thread_uid = :thread_uid";
		$this->databaseConnection->execute($query, $params);
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
	
	public function resolvePostUidFromPostNumber($board, $postNumber) {
		$query = "SELECT post_uid FROM {$this->tablename} WHERE no = :post_number AND boardUID = :board_uid";
		$params = [
			':post_number' => strval($postNumber),
			':board_uid' => $board->getBoardUID()
		];
		$postUID = $this->databaseConnection->fetchColumn($query, $params);
		return $postUID;
	}
	
	public function resolveThreadNumberFromUID($thread_uid) {
		$query = "SELECT post_op_number FROM {$this->threadTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => strval($thread_uid)
		];
		$threadNo = $this->databaseConnection->fetchColumn($query, $params);
		return $threadNo;
	}
	
	public function resolvePostNumberFromUID($post_uid) {
		$query = "SELECT no FROM {$this->tablename} WHERE post_uid = :post_uid";
		$params = [
			':post_uid' => strval($post_uid)
		];
		$postNo = $this->databaseConnection->fetchColumn($query, $params);
		return $postNo;
	}
	
	/* Delete attachments */
	public function removeAttachments($posts, $recursion = false) {
		$FileIO = PMCLibrary::getFileIOInstance();
		if (empty($posts)) return [];
		if (!is_array($posts)) $posts = [$posts];

		addSlashesToArray($posts);

		$placeholders = implode(', ', $posts);

		// Construct the SQL query
		$query = $recursion
			? "SELECT ext, tim, boardUID 
			   FROM {$this->tablename} 
			   WHERE 
				   post_uid IN ($placeholders)
				   OR thread_uid IN ($placeholders)
				   OR post_uid IN (
					   SELECT post_uid 
					   FROM {$this->tablename} 
					   WHERE thread_uid IN (
						   SELECT thread_uid 
						   FROM {$this->threadTable} 
						   WHERE post_op_post_uid IN ($placeholders)
					   )
			   	)
			   AND ext <> ''"
			: "SELECT ext, tim, boardUID 
			   FROM {$this->tablename} 
			   WHERE 
				   post_uid IN ($placeholders)
				   OR thread_uid IN ($placeholders)
				   AND ext <> ''";

		$results = $this->databaseConnection->fetchAllAsArray($query);

		$files = [];
		foreach ($results as $row) {
			$board = searchBoardArrayForBoard($this->loadedBoards, $row['boardUID']);

			$dfile = $row['tim'] . $row['ext'];
			$dthumb = $FileIO->resolveThumbName($row['tim'], $board);
			if ($FileIO->imageExists($dfile, $board)) $files[] = $dfile;
			if ($dthumb && $FileIO->imageExists($dthumb, $board)) $files[] = $dthumb;
		}

		return $files;
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
				$updatePostQuery = "UPDATE {$this->tablename} 
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
}
