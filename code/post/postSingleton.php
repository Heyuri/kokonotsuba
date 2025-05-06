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
	private array $allowedOrderFields;


	public function __construct($dbSettings){
		$boardIO = boardIO::getInstance();
		
		$this->tablename = $dbSettings['POST_TABLE'];
		$this->threadTable = $dbSettings['THREAD_TABLE'];
		
		$this->loadedBoards = $boardIO->getAllBoards();
		
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance

		$this->allowedOrderFields = ['post_uid', 'root', 'no'];
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

	public function getAllPosts() {
		$query = "SELECT * FROM {$this->tablename} ORDER BY post_uid DESC";
		$posts = $this->databaseConnection->fetchAllAsArray($query);
		return $posts;
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
	
	/* Add a new post to a thread */
	public function addPost(board $board, int $no, string $thread_uid_from_url, int $post_position, bool $is_op, string $md5chksum, string $category, int $tim, string $fname, string $ext, int $imgw, int $imgh, 
		string $imgsize, int $tw, int $th, string $pwd, string $now, string $name, string $tripcode, string $secure_tripcode, string $capcode, string $email, string $sub, string $com, string $host,  bool $age = false, string $status = '') {
		
		$threadSingleton = threadSingleton::getInstance();

		$this->beginTransaction();
		try {
			$boardUID = $board->getBoardUID();
			$time = (int)substr($tim, 0, -3);
			$root = gmdate('Y-m-d H:i:s');
			$postUID = $this->getNextPostUid();
			$thread_uid_for_database = null;
			$isThread = false;
			
			$board->incrementBoardPostNumber();
			
			if(!$thread_uid_from_url) {
				//create a new thread
				$thread_uid = generateUid();
				$threadSingleton->addThread($boardUID, $postUID, $thread_uid, $no);
				$thread_uid_for_database = $thread_uid;
				$isThread = true;
			} else {
				//post to an existing thread
				$thread_uid_for_database = $thread_uid_from_url;
			}
			
			$query = "INSERT INTO {$this->tablename} 
				(no, boardUID, thread_uid, post_position, is_op, root, time, md5chksum, 
				category, tim, fname, ext, imgw, imgh, imgsize, tw, th, pwd, now, 
				name, tripcode, secure_tripcode, capcode, email, sub, com, host, status) 
				VALUES (:no, :boardUID, :thread_uid, :post_position, :is_op, :root, :time,
				:md5chksum, :category, :tim, :fname, :ext, :imgw, :imgh, :imgsize, :tw, :th, 
				:pwd, :now, :name, :tripcode, :secure_tripcode, :capcode, :email, :sub, :com, :host, :status)";
		
			$params = [
				':no'          => $no,
				':boardUID'    => $boardUID,
				':thread_uid'  => $thread_uid_for_database,
				':post_position' => $post_position,
				':is_op'	   => (int)$is_op,
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
				':tripcode'    => $tripcode,
				':secure_tripcode' => $secure_tripcode,
				':capcode'	   => $capcode,
				':email'       => $email,
				':sub'         => $sub,
				':com'         => $com,
				':host'        => $host,
				':status'      => $status
			];

			$this->databaseConnection->execute($query, $params);

			if ($age || $isThread) $threadSingleton->bumpThread($thread_uid_for_database);
			else $threadSingleton->updateThreadLastReplyTime($thread_uid_for_database);
			$this->commit();
		} catch (Exception $e) {
			$this->rollBack();
			throw $e;
		}
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

	public function getThreadPreviewsFromBoard(board $board, int $previewCount, int $amount = 0, int $offset = 0): array {
		$boardUID = $board->getBoardUID();
	
		// Sanitize LIMIT and OFFSET values
		$amount = max(0, $amount);
		$offset = max(0, $offset);
	
		// Step 1: fetch paginated threads with OP post + status
		$query = "
			SELECT t.thread_uid, t.post_op_post_uid, t.post_op_number, p.status, p.boardUID
			FROM {$this->threadTable} t
			JOIN {$this->tablename} p ON t.post_op_post_uid = p.post_uid
			WHERE t.boardUID = :boardUID
			ORDER BY t.last_bump_time DESC
		";

		if($amount) {
			$query .= " LIMIT $amount OFFSET $offset";
		}
	
		$threads = $this->databaseConnection->fetchAllAsArray($query, [
			':boardUID' => $boardUID
		]);
	
		if (empty($threads)) return [];
	
		$threadUIDs = array_column($threads, 'thread_uid');
	
		// Step 2: fetch all posts for these threads
		$inClause = implode(',', array_fill(0, count($threadUIDs), '?'));
		$postQuery = "
			SELECT *
			FROM {$this->tablename}
			WHERE thread_uid IN ($inClause)
			ORDER BY thread_uid, post_position ASC
		";
		$postRows = $this->databaseConnection->fetchAllAsArray($postQuery, $threadUIDs);
	
		// Step 3: group posts by thread_uid
		$postsByThread = [];
		foreach ($postRows as $post) {
			$postsByThread[$post['thread_uid']][] = $post;
		}
	
		// Step 4: assemble results
		$result = [];
		foreach ($threads as $thread) {
			$threadUID = $thread['thread_uid'];
			$allPosts = $postsByThread[$threadUID] ?? [];
	
			$totalPosts = count($allPosts);
			$omittedCount = max(0, $totalPosts - $previewCount - 1);
	
			$opPost = array_filter($allPosts, fn($p) => $p['is_op']);
			$nonOpPosts = array_filter($allPosts, fn($p) => !$p['is_op']);
			$previewPosts = array_merge(
				$opPost,
				array_slice($nonOpPosts, max(0, count($nonOpPosts) - $previewCount))
			);
	
			$result[] = [
				'thread' => $thread,
				'post_uids' => array_column($previewPosts, 'post_uid'),
				'posts' => $previewPosts,
				'hidden_reply_count' => $omittedCount,
				'thread_uid' => $threadUID
			];
		}
	
		return $result;
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
	}
		/* Get number of posts */
	public function postCount($filters = []) {
		$query = "SELECT COUNT(post_uid) FROM {$this->tablename} WHERE 1 ";
		$params = [];
		bindfiltersParameters($params, $query, $filters);
		
		return $this->databaseConnection->fetchColumn($query, $params);
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

	public function getPostsFromBoard(board $board, int $start = 0, int $amount = 0, string $order = "no", string $sortOrder = "DESC"): array {
		if(!in_array($order, $this->allowedOrderFields)) return [];
		
		$query = "SELECT * FROM {$this->tablename} WHERE boardUID = :board_uid ORDER BY $order $sortOrder";
		if($amount) {
			$query .= " LIMIT $start, $amount";
		}
		$params[':board_uid'] = intval($board->getBoardUID());
		return $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];
	}
	
	public function getPostsFromIP(string $host, int $limit = 10, int $offset = 0, string $order = "post_uid"): array {
		if(!in_array($order, $this->allowedOrderFields)) return [];
		
		// Ensure limit is not negative
		$limit = max(1, $limit);
		// Ensure offset is not negative
		$offset = max(0, $offset);
		
		$query = "SELECT * FROM {$this->tablename} WHERE host = :ip_address ORDER BY $order LIMIT $limit OFFSET $offset";
		$params = [
			':ip_address' => $host,
		];
		
		// Fetch results from the database and return them as an array, or an empty array if no results
		return $this->databaseConnection->fetchAllAsArray($query, $params) ?? [];
	}	
	
	public function getFilteredPosts(int $amount, int $offset = 0, array $filters = [], string $order = 'post_uid'): array {
		if(!in_array($order, $this->allowedOrderFields)) return [];

		$query = "SELECT * FROM {$this->tablename} WHERE 1";
		$params = [];
		
		bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		return $posts ?? [];
	}
	
	/* Output posts for multiple boards and threads */
	public function fetchPostsFromBoardsAndThreads(array $boardThreadMap, string $fields = '*') {
		if (empty($boardThreadMap)) {
			return array();
		}

		foreach ($boardThreadMap as $boardUID => $threadIDs) {
			if (empty($threadIDs)) {
				unset($boardThreadMap[$boardUID]);
			}
		}

		$conditions = array();
		$params = array();

		foreach ($boardThreadMap as $boardUID => $threadIDs) {
			if (empty($threadIDs)) {
				continue;
			}

			$placeholders = implode(',', array_fill(0, count($threadIDs), '?'));
			$conditions[] = "(boardUID = ? AND (post_uid IN ({$placeholders}) OR thread_uid IN ({$placeholders})))";

			// First placeholder is boardUID
			$params[] = $boardUID;
			// Next placeholders are threadIDs twice (post_uid and thread_uid)
			foreach ($threadIDs as $threadID) {
				$params[] = $threadID;
			}
			foreach ($threadIDs as $threadID) {
				$params[] = $threadID;
			}
		}

		if (empty($conditions)) {
			return array();
		}

		$whereClause = implode(' OR ', $conditions);
		$query = "SELECT {$fields} FROM {$this->tablename} WHERE {$whereClause}";

		return $this->databaseConnection->fetchAllAsArray($query, $params);
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

				if (!$newReplyData) {
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
	
	public function isThreadOP($post_uid) {
		$query = "SELECT post_op_post_uid FROM {$this->threadTable} WHERE post_op_post_uid = :post_op_post_uid";
		$params = [
			':post_op_post_uid' => strval($post_uid)
		];
		$threadExists = $this->databaseConnection->fetchColumn($query, $params) ? true : false;
		return $threadExists;
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
	
	public function resolvePostUidFromPostNumber($board, $postNumber) {
		$query = "SELECT post_uid FROM {$this->tablename} WHERE no = :post_number AND boardUID = :board_uid";
		$params = [
			':post_number' => strval($postNumber),
			':board_uid' => $board->getBoardUID()
		];
		$postUID = $this->databaseConnection->fetchColumn($query, $params);
		return $postUID;
	}
	
	public function resolvePostUidsFromArray($board, array $postNumbers): array {
		if (empty($postNumbers)) {
			return [];
		}

		$board_uid = $board->getBoardUid();
	
		// Sanitize and deduplicate post numbers
		$sanitizedNumbers = array_unique(array_map('intval', $postNumbers));
		$inClause = implode(',', $sanitizedNumbers);
	
		$query = "
			SELECT no, post_uid
			FROM {$this->tablename}
			WHERE no IN ($inClause)
			AND boardUID = :board_uid";
	
		$params = [
			':board_uid' => $board_uid
		];

		$rows = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		// Map post_number (no) => post_uid
		$resolved = [];
		foreach ($rows as $row) {
			$resolved[(int)$row['no']] = (int)$row['post_uid'];
		}
	
		return $resolved;
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

	public function getNextPostUid(): int {
		return $this->databaseConnection->getNextAutoIncrement($this->tablename);
	}

}

