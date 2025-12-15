<?php

class postRepository {
	private array $allowedOrderFields;

	public function __construct(
		private databaseConnection $databaseConnection, 
		private readonly string $postTable, 
		private readonly string $threadTable,
		private readonly string $deletedPostsTable,
		private readonly string $fileTable
	) {
		$this->allowedOrderFields = ['root' , 'no', 'post_uid'];
	}

	public function getLastInsertPostUid(): mixed {
		return $this->databaseConnection->lastInsertId();
	}

		/* Get number of posts */
	public function postCountFromBoard($board, $threadUID = 0) {
		if ($threadUID) {
			$query = "SELECT COUNT(post_uid) FROM {$this->postTable} WHERE thread_uid = ?";
			$count = $this->databaseConnection->fetchColumn($query, [$threadUID]);
			return $count + 1;
		} else {
			$query = "SELECT COUNT(post_uid) FROM {$this->postTable} WHERE boardUID = :board_uid";
			return $this->databaseConnection->fetchColumn($query, [':board_uid' => $board->getBoardUID()]);
		}
	}

	/* Get number of posts */
	public function postCount($filters = []) {
		$query = "SELECT COUNT(post_uid) FROM posts WHERE 1";

		$params = [];
		bindPostFilterParameters($params, $query, $filters);
		
		return $this->databaseConnection->fetchColumn($query, $params);
	}

	public function getFilteredPosts(int $amount, int $offset = 0, array $filters = [], bool $includeDeleted = false, string $order = 'post_uid'): false|array {
		if(!in_array($order, $this->allowedOrderFields)) return [];

		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);
		$params = [];
		
		// add WHERE so the AND conditions can be appended without sissue
		$query .= " WHERE 1";

		bindPostFilterParameters($params, $query, $filters, true); //apply filtration to query

		// exclude deleted posts
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		$query .= " ORDER BY p.$order  DESC LIMIT $amount OFFSET $offset";
		$posts = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

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

			$inClause = pdoPlaceholdersForIn($threadIDs);
			$conditions[] = "(boardUID = ? AND (post_uid IN $inClause OR thread_uid IN $inClause))";

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
		$query = "SELECT {$fields} FROM {$this->postTable} WHERE {$whereClause}";

		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	public function getPostByUid(int $post_uid): array|false {
		// get base post query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);
		
		// append WHERE clause to get it by the post uid
		$query .= " WHERE p.post_uid = :post_uid";
		
		// parameter
		$params = [
			':post_uid' => $post_uid
		];

		// fetch row(s)
		// it has to have fetch all instead of fetchOne because multiple attachments = multiple rows returned
		// its up to mergeMultiplePostRows to take care of those extra attachment rows
		$post = $this->databaseConnection->fetchAllAsArray($query, $params);

		// merge attachment row
		$post = mergeMultiplePostRows($post);

		// return row
		// make sure to return the first
		return $post[0] ?? false;
	}

	public function getNextPostUid(): int {
		return $this->databaseConnection->getNextAutoIncrement($this->postTable);
	}

	public function resolvePostNumberFromUID($post_uid) {
		$query = "SELECT no FROM {$this->postTable} WHERE post_uid = :post_uid";
		$params = [
			':post_uid' => strval($post_uid)
		];
		$postNo = $this->databaseConnection->fetchColumn($query, $params);
		return $postNo;
	}
	
	public function resolvePostUidsFromArray($board, array $postNumbers): array {
		if (empty($postNumbers)) {
			return [];
		}

		$board_uid = $board->getBoardUid();
	
		// Sanitize and deduplicate post numbers
		$sanitizedNumbers = array_unique(array_map('intval', $postNumbers));

		// Get IN clause placeholders and parameter bindings
		$inParams = [];
		$inClause = '(' . implode(', ', array_map(function($i) use (&$inParams, $sanitizedNumbers) {
		    $param = ":no_$i";
		    $inParams[$param] = $sanitizedNumbers[$i];
		    return $param;
		}, array_keys($sanitizedNumbers))) . ')';

		$query = "
    		SELECT no, post_uid
		    FROM {$this->postTable}
		    WHERE no IN $inClause
		    AND boardUID = :board_uid";

		// Merge board UID with IN clause parameters
		$params = array_merge([':board_uid' => $board_uid], $inParams);

		$rows = $this->databaseConnection->fetchAllAsArray($query, $params);

		// Map post_number (no) => post_uid
		$resolved = [];
		foreach ($rows as $row) {
			$resolved[(int)$row['no']] = (int)$row['post_uid'];
		}
	
		return $resolved;
	}

	public function resolvePostUidFromPostNumber($board, $postNumber) {
		$query = "SELECT post_uid FROM {$this->postTable} WHERE no = :post_number AND boardUID = :board_uid";
		$params = [
			':post_number' => strval($postNumber),
			':board_uid' => $board->getBoardUID()
		];
		$postUID = $this->databaseConnection->fetchColumn($query, $params);
		return $postUID;
	}

	/* Set the status of a post */
	public function setPostStatus($post_uid, $newStatus) {
		$query = "UPDATE {$this->postTable} SET status = ? WHERE post_uid = ?";
		$params = [$newStatus, strval($post_uid)];
		$this->databaseConnection->execute($query, $params);
			
		return true;
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
		$query = "UPDATE {$this->postTable} SET " . implode(', ', $setClause) . " WHERE post_uid = ?";
		$this->databaseConnection->execute($query, $params);
	}

	public function insertPost(array $params): void {
		$query = "INSERT INTO {$this->postTable} 
			(no, poster_hash, boardUID, thread_uid, post_position, is_op, root, category, pwd, now, 
			name, tripcode, secure_tripcode, capcode, email, sub, com, host, status) 
			VALUES (:no, :poster_hash, :boardUID, :thread_uid, :post_position, :is_op, :root,
			:category, :pwd, :now, :name, :tripcode, :secure_tripcode, :capcode, :email, :sub, :com, :host, :status)";
		
		$this->databaseConnection->execute($query, $params);
	}

	public function getMaxPostPosition(string $threadUID): ?int {
		$query = "SELECT MAX(post_position) FROM {$this->postTable} WHERE thread_uid = :thread_uid";
		return $this->databaseConnection->fetchValue($query, [':thread_uid' => $threadUID]);
	}

	public function getOpPostEmailAndStatus(string $threadUID): array|false {
		return $this->databaseConnection->fetchOne("
			SELECT email, status
			FROM {$this->postTable}
			WHERE post_uid = (
				SELECT post_op_post_uid
				FROM {$this->threadTable}
				WHERE thread_uid = ?
			)
			LIMIT 1
		", [$threadUID]);
	}

	public function getPostsByUids(array $postUIDsList): array|false {
		// get base query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// generate in clause
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		// append where clause
		$query .= " WHERE p.post_uid IN $inClause";

		// fetch posts
		$posts = $this->databaseConnection->fetchAllAsArray($query, $postUIDsList);

		// merge multiple rows
		$posts = mergeMultiplePostRows($posts);
		
		// return posts
		return $posts;
	}

	public function getThreadUIDsByPostUIDs(array $postUIDsList): ?string {
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		return $this->databaseConnection->fetchColumn("
			SELECT DISTINCT thread_uid
			FROM {$this->postTable}
			WHERE post_uid IN $inClause
		", $postUIDsList);
	}

	public function deletePostsByUIDs(array $postUIDsList): void {
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		$this->databaseConnection->execute("
			DELETE FROM {$this->postTable}
			WHERE post_uid IN $inClause
		", $postUIDsList);
	}
	
	public function getPostsByThreadUIDs(array $threadUids, bool $includeDeleted = false): array|false {	
		$inClause = pdoPlaceholdersForIn($threadUids);

		// base post query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// append where clause
		$query .= " WHERE p.thread_uid IN $inClause";

		// exlude deleted posts
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		// fetch post rows
		$posts = $this->databaseConnection->fetchAllAsArray($query, $threadUids);

		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		return $posts;
	}

	public function getPostUidsFromThread(string $threadUid): bool|array {
		$query = "SELECT post_uid FROM {$this->postTable} WHERE thread_uid = :thread_uid";

		$params = [
			':thread_uid' => $threadUid
		];

		$postUids = array_merge(...$this->databaseConnection->fetchAllAsIndexArray($query, $params));

		return $postUids;
	}

	public function getOpeningPostFromThread(string $threadUid): bool|array {
		// get base post query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->threadTable);

		// append WHERE clause
		$query .= " WHERE p.post_uid = (SELECT post_op_post_uid FROM {$this->threadTable} WHERE thread_uid = :thread_uid)";

		$params = [
			':thread_uid' => $threadUid
		];

		$post = $this->databaseConnection->fetchAllAsArray($query, $params);
	
		// merge data
		$post = mergeMultiplePostRows($post)[0];

		return $post;
	}

	public function getUniquePairFromPostUids(array $postUIDsList): array|false {
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		$query = "
			SELECT DISTINCT thread_uid, boardUID
			FROM {$this->postTable}
			WHERE post_uid IN $inClause
		";

		$pair = $this->databaseConnection->fetchAllAsArray($query, $postUIDsList);
		
		return $pair;
	}


	/**
	 * Insert posts in batch (DTO params) and return assigned post_uids.
	 *
	 * @param array $posts Array of param arrays from postRegistData::toParams()
	 * @return array post_uids in same order
	 */
	public function insertPostsBatch(array $posts): array {
		if (empty($posts)) return [];

		// Manually define the columns
		$columns = [
			'no', 'poster_hash', 'boardUID', 'thread_uid', 'post_position', 'is_op',
			'root', 'category', 'pwd', 'now', 'name', 'tripcode', 'secure_tripcode',
			'capcode', 'email', 'sub', 'com', 'host', 'status'
		];

		// Reindex the array
		$posts = array_values($posts);

		// Create the column list for the query
		$fieldList = implode(', ', $columns);

		$rows = [];
		
		foreach ($posts as $post) {
			// get param array keys
			$params = array_keys($post);

			// Add the placeholders for this row
			$rows[] = '(' . implode(', ', $params) . ')';
		}

		// Construct the full SQL query
		$query = "INSERT INTO {$this->postTable} ($fieldList) VALUES " . implode(',', $rows);

		// flatten into 1d array so we can pass it as regular query params
		$paramsForQuery = array_merge(...$posts);

		//echo '<br><br><br>'; echo '<pre>'; echo $query . '<br>'; print_r($paramsForQuery); echo '</pre>';

		// Execute the query with the parameters
		$this->databaseConnection->execute($query, $paramsForQuery);

		// Get the first inserted ID and generate the range of post_uids
		$firstId = $this->databaseConnection->lastInsertId();
		return range($firstId, $firstId + count($posts) - 1);
	}

	/**
	 * Get next post UID once and then increment locally.
	 *
	 * @param int $count Number of UIDs you need
	 * @return array Array of post_uids
	 */
	public function getNextPostUids(int $count): array {
		$startUid = $this->getNextPostUid();
		return range($startUid, $startUid + $count - 1);
	}

}