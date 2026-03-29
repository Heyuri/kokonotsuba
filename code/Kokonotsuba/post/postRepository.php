<?php

namespace Kokonotsuba\post;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\bindPostFilterParameters;
use function Kokonotsuba\libraries\getBasePostQuery;
use function Kokonotsuba\libraries\mergeMultiplePostRows;
use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for post records, supporting full-text retrieval, batch insertion, and deletion. */
class postRepository extends baseRepository {
	private array $allowedOrderFields;

	public function __construct(
		databaseConnection $databaseConnection, 
		string $postTable, 
		private readonly string $threadTable,
		private readonly string $deletedPostsTable,
		private readonly string $fileTable,
		private readonly string $soudaneTable,
		private readonly string $noteTable,
		private readonly string $accountTable,
	) {
		parent::__construct($databaseConnection, $postTable);
		$this->allowedOrderFields = ['root' , 'no', 'post_uid'];
	}

	/**
	 * Return the post_uid of the most recently inserted post.
	 *
	 * @return mixed Last inserted post_uid value.
	 */
	public function getLastInsertPostUid(): mixed {
		return $this->lastInsertId();
	}

	/**
	 * Return the post count for a board, or for a specific thread within a board.
	 *
	 * @param mixed  $board     Board object with a getBoardUID() method.
	 * @param int    $threadUID Thread UID to count replies for, or 0 for board-wide count.
	 * @return int Post count (thread count includes +1 for the OP).
	 */
	public function postCountFromBoard($board, $threadUID = 0) {
		if ($threadUID) {
			$query = "SELECT COUNT(post_uid) FROM {$this->table} WHERE thread_uid = ?";
			$count = $this->queryColumn($query, [$threadUID]);
			return $count + 1;
		} else {
			$query = "SELECT COUNT(post_uid) FROM {$this->table} WHERE boardUID = :board_uid";
			return $this->queryColumn($query, [':board_uid' => $board->getBoardUID()]);
		}
	}

	/**
	 * Return the total post count, optionally filtered by the given criteria.
	 *
	 * @param array $filters Optional key/value filter criteria.
	 * @return int Post count.
	 */
	public function postCount($filters = []) {
		$query = "SELECT COUNT(post_uid) FROM {$this->table} WHERE 1";

		$params = [];
		bindPostFilterParameters($params, $query, $filters);
		
		return $this->queryColumn($query, $params);
	}

	/**
	 * Fetch a filtered, paginated list of posts with merged attachment rows.
	 *
	 * @param int    $amount         Number of posts to return.
	 * @param int    $offset         Pagination offset.
	 * @param array  $filters        Optional filter criteria array.
	 * @param bool   $includeDeleted Whether to include soft-deleted posts.
	 * @param string $order          Column to order by (validated against allowed list).
	 * @return array|false Array of merged post data arrays, or false/empty if none.
	 */
	public function getFilteredPosts(int $amount, int $offset = 0, array $filters = [], bool $includeDeleted = false, string $order = 'post_uid'): false|array {
		if(!in_array($order, $this->allowedOrderFields)) return [];

		$query = getBasePostQuery($this->table, $this->deletedPostsTable, $this->fileTable, $this->threadTable, $this->soudaneTable, $this->noteTable, $this->accountTable,  $includeDeleted);
		$params = [];
		
		// add WHERE so the AND conditions can be appended without sissue
		$query .= " WHERE 1";

		bindPostFilterParameters($params, $query, $filters, true); //apply filtration to query

		$query .= " ORDER BY p.$order  DESC LIMIT :_limit OFFSET :_offset";
		$params[':_limit'] = $amount;
		$params[':_offset'] = $offset;
		$posts = $this->queryAll($query, $params);
	
		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		return $posts ?? [];
	}

	/**
	 * Fetch posts belonging to the specified boards and threads.
	 *
	 * @param array  $boardThreadMap Map of boardUID => array of thread/post UIDs.
	 * @param string $fields         Comma-separated column list to SELECT.
	 * @return array Array of raw post rows.
	 */
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
		$query = "SELECT {$fields} FROM {$this->table} WHERE {$whereClause}";

		return $this->queryAll($query, $params);
	}

	/**
	 * Fetch a single post by its UID, with merged attachment rows.
	 *
	 * @param int  $post_uid     Post UID.
	 * @param bool $viewDeleted  Include deletion metadata in the query.
	 * @return array|false Merged post data array, or false if not found.
	 */
	public function getPostByUid(int $post_uid, bool $viewDeleted = false): array|false {
		// get base post query
		$query = getBasePostQuery($this->table, $this->deletedPostsTable, $this->fileTable, $this->threadTable, $this->soudaneTable, $this->noteTable, $this->accountTable,  $viewDeleted);
		
		// append WHERE clause to get it by the post uid
		$query .= " WHERE p.post_uid = :post_uid";
		
		// parameter
		$params = [
			':post_uid' => $post_uid
		];

		// fetch row(s)
		// it has to have fetch all instead of fetchOne because multiple attachments = multiple rows returned
		// its up to mergeMultiplePostRows to take care of those extra attachment rows
		$post = $this->queryAll($query, $params);

		// merge attachment row
		$post = mergeMultiplePostRows($post);

		// return row
		// make sure to return the first
		return $post[0] ?? false;
	}

	/**
	 * Return the next AUTO_INCREMENT value for the posts table.
	 *
	 * @return int Next available post_uid.
	 */
	public function getNextPostUid(): int {
		return $this->getNextAutoIncrement();
	}

	/**
	 * Resolve a post number (no) from a post UID.
	 *
	 * @param mixed $post_uid Post UID.
	 * @return mixed Post number (no), or null if not found.
	 */
	public function resolvePostNumberFromUID($post_uid) {
		$query = "SELECT no FROM {$this->table} WHERE post_uid = :post_uid";
		$params = [
			':post_uid' => strval($post_uid)
		];
		$postNo = $this->queryColumn($query, $params);
		return $postNo;
	}
	
	/**
	 * Resolve multiple post UIDs from their post numbers (no) within a specific board.
	 *
	 * @param mixed $board       Board object with getBoardUid().
	 * @param array $postNumbers Array of post numbers to resolve.
	 * @return array Map of post_number => post_uid.
	 */
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
		    FROM {$this->table}
		    WHERE no IN $inClause
		    AND boardUID = :board_uid";

		// Merge board UID with IN clause parameters
		$params = array_merge([':board_uid' => $board_uid], $inParams);

		$rows = $this->queryAll($query, $params);

		// Map post_number (no) => post_uid
		$resolved = [];
		foreach ($rows as $row) {
			$resolved[(int)$row['no']] = (int)$row['post_uid'];
		}
	
		return $resolved;
	}

	/**
	 * Resolve a single post UID from a post number within a specific board.
	 *
	 * @param mixed $board      Board object with getBoardUID().
	 * @param mixed $postNumber Post number (no) to look up.
	 * @return mixed Post UID, or null if not found.
	 */
	public function resolvePostUidFromPostNumber($board, $postNumber) {
		$query = "SELECT post_uid FROM {$this->table} WHERE no = :post_number AND boardUID = :board_uid";
		$params = [
			':post_number' => strval($postNumber),
			':board_uid' => $board->getBoardUID()
		];
		$postUID = $this->queryColumn($query, $params);
		return $postUID;
	}

	/**
	 * Update the status column for the given post.
	 *
	 * @param mixed $post_uid  Post UID.
	 * @param mixed $newStatus New status value.
	 * @return true Always returns true.
	 */
	public function setPostStatus($post_uid, $newStatus) {
		$this->updateWhere(['status' => $newStatus], 'post_uid', strval($post_uid));
		return true;
	}

	/**
	 * Update arbitrary columns for the given post UID.
	 *
	 * @param mixed $post_uid   Post UID.
	 * @param array $newValues  Associative array of column => new value pairs.
	 * @return void
	 */
	public function updatePost($post_uid, $newValues) {
		$this->updateWhere($newValues, 'post_uid', strval($post_uid));
	}

	/**
	 * Insert a new post row using the given parameter array.
	 *
	 * @param array $params Named parameter array matching the posts table columns.
	 * @return void
	 */
	public function insertPost(array $params): void {
		$query = "INSERT INTO {$this->table} 
			(no, poster_hash, boardUID, thread_uid, post_position, is_op, root, category, pwd, now, 
			name, tripcode, secure_tripcode, capcode, email, sub, com, host, status) 
			VALUES (:no, :poster_hash, :boardUID, :thread_uid, :post_position, :is_op, :root,
			:category, :pwd, :now, :name, :tripcode, :secure_tripcode, :capcode, :email, :sub, :com, :host, :status)";
		
		$this->query($query, $params);
	}

	/**
	 * Return the highest post_position value in the given thread.
	 *
	 * @param string $threadUID Thread UID.
	 * @return int|null Maximum position, or null if the thread has no posts.
	 */
	public function getMaxPostPosition(string $threadUID): ?int {
		$query = "SELECT MAX(post_position) FROM {$this->table} WHERE thread_uid = :thread_uid";
		return $this->queryValue($query, [':thread_uid' => $threadUID]);
	}

	/**
	 * Return the email and status columns of the OP post for the given thread.
	 *
	 * @param string $threadUID Thread UID.
	 * @return array|false Associative row with 'email' and 'status', or false if not found.
	 */
	public function getOpPostEmailAndStatus(string $threadUID): array|false {
		return $this->queryOne("
			SELECT email, status
			FROM {$this->table}
			WHERE post_uid = (
				SELECT post_op_post_uid
				FROM {$this->threadTable}
				WHERE thread_uid = ?
			)
			LIMIT 1
		", [$threadUID]);
	}

	/**
	 * Fetch multiple posts by their UIDs, with merged attachment rows.
	 *
	 * @param int[] $postUIDsList Array of post UIDs.
	 * @return array|false Array of merged post data arrays, or false if not found.
	 */
	public function getPostsByUids(array $postUIDsList): array|false {
		// get base query
		$query = getBasePostQuery($this->table, $this->deletedPostsTable, $this->fileTable, $this->threadTable, $this->soudaneTable, $this->noteTable, $this->accountTable);

		// generate in clause
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		// append where clause
		$query .= " WHERE p.post_uid IN $inClause";

		// fetch posts
		$posts = $this->queryAll($query, $postUIDsList);

		// merge multiple rows
		$posts = mergeMultiplePostRows($posts);
		
		// return posts
		return $posts;
	}

	/**
	 * Return the distinct thread_uid for the given post UIDs (returns only the first match via queryColumn).
	 *
	 * @param int[] $postUIDsList Array of post UIDs.
	 * @return string|null Thread UID, or null if none found.
	 */
	public function getThreadUIDsByPostUIDs(array $postUIDsList): ?string {
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		return $this->queryColumn("
			SELECT DISTINCT thread_uid
			FROM {$this->table}
			WHERE post_uid IN $inClause
		", $postUIDsList);
	}

	/**
	 * Permanently delete posts for the given post UIDs.
	 *
	 * @param int[] $postUIDsList Array of post UIDs to delete.
	 * @return void
	 */
	public function deletePostsByUIDs(array $postUIDsList): void {
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		$this->query("
			DELETE FROM {$this->table}
			WHERE post_uid IN $inClause
		", $postUIDsList);
	}
	
	/**
	 * Fetch all posts belonging to the given thread UIDs, with merged attachment rows.
	 *
	 * @param string[] $threadUids     Array of thread UIDs.
	 * @param bool     $includeDeleted Whether to include soft-deleted posts.
	 * @return array|false Array of merged post data arrays, or false if none found.
	 */
	public function getPostsByThreadUIDs(array $threadUids, bool $includeDeleted = false): array|false {	
		$inClause = pdoPlaceholdersForIn($threadUids);

		// base post query
		$query = getBasePostQuery($this->table, $this->deletedPostsTable, $this->fileTable, $this->threadTable, $this->soudaneTable, $this->noteTable, $this->accountTable,  $includeDeleted);

		// append where clause
		$query .= " WHERE p.thread_uid IN $inClause";

		// fetch post rows
		$posts = $this->queryAll($query, $threadUids);

		// merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		return $posts;
	}

	/**
	 * Return a flat array of all post UIDs belonging to the given thread.
	 *
	 * @param string $threadUid Thread UID.
	 * @return bool|array Flat array of post UIDs, or false if none found.
	 */
	public function getPostUidsFromThread(string $threadUid): bool|array {
		$query = "SELECT post_uid FROM {$this->table} WHERE thread_uid = :thread_uid";

		$params = [
			':thread_uid' => $threadUid
		];

		$postUids = array_merge(...$this->queryAllAsIndexArray($query, $params));

		return $postUids;
	}

	/**
	 * Fetch the OP (opening post) for the given thread, with merged attachment rows.
	 *
	 * @param string $threadUid Thread UID.
	 * @return bool|array Merged post data array, or false if not found.
	 */
	public function getOpeningPostFromThread(string $threadUid): bool|array {
		// get base post query
		$query = getBasePostQuery($this->table, $this->deletedPostsTable, $this->fileTable, $this->threadTable, $this->soudaneTable, $this->noteTable, $this->accountTable);

		// append WHERE clause
		$query .= " WHERE p.post_uid = (SELECT post_op_post_uid FROM {$this->threadTable} WHERE thread_uid = :thread_uid)";

		$params = [
			':thread_uid' => $threadUid
		];

		$post = $this->queryAll($query, $params);
	
		// merge data
		$post = mergeMultiplePostRows($post)[0];

		return $post;
	}

	/**
	 * Return distinct (thread_uid, boardUID) pairs for the given post UIDs.
	 *
	 * @param int[] $postUIDsList Array of post UIDs.
	 * @return array|false Array of rows with 'thread_uid' and 'boardUID', or false if none found.
	 */
	public function getUniquePairFromPostUids(array $postUIDsList): array|false {
		$inClause = pdoPlaceholdersForIn($postUIDsList);

		$query = "
			SELECT DISTINCT thread_uid, boardUID
			FROM {$this->table}
			WHERE post_uid IN $inClause
		";

		$pair = $this->queryAll($query, $postUIDsList);
		
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
		$query = "INSERT INTO {$this->table} ($fieldList) VALUES " . implode(',', $rows);

		// flatten into 1d array so we can pass it as regular query params
		$paramsForQuery = array_merge(...$posts);

		//echo '<br><br><br>'; echo '<pre>'; echo $query . '<br>'; print_r($paramsForQuery); echo '</pre>';

		// Execute the query with the parameters
		$this->query($query, $paramsForQuery);

		// Get the first inserted ID and generate the range of post_uids
		$firstId = $this->lastInsertId();
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


	/**
	 * Fetch post UIDs that have the exact same comment content within a given time window.
	 *
	 * This is primarily used for spam detection by identifying repeated posts
	 * made in a short period of time.
	 *
	 * - Matches posts by exact comment equality
	 * - Restricts results to posts newer than the given time window (in seconds)
	 * - Optionally excludes a known default comment if provided
	 *
	 * @param string      $comment        The comment content to match against existing posts
	 * @param string|null $defaultComment A default/boilerplate comment to exclude, or null to disable exclusion
	 * @param int         $timeWindow     Time window in seconds to look back from now
	 *
	 * @return array|null Returns an array of matching post UIDs, or null if none are found
	 */
	public function getRepeatedPosts(string $comment, ?string $defaultComment, int $timeWindow): ?array {
		// Base query: find posts with the same comment within the given time window
		$query = "
			SELECT post_uid
			FROM {$this->table}
			WHERE com = :comment
			AND root >= (UTC_TIMESTAMP() - INTERVAL :timeWindow SECOND)
		";

		// Base parameters for the query
		$params = [
			':comment' => $comment,
			':timeWindow' => $timeWindow
		];

		// If a default comment is provided, explicitly exclude it
		// This allows callers to skip spam checks for known boilerplate content
		if ($defaultComment !== null) {
			$query .= " AND com != :defaultComment";
			$params[':defaultComment'] = $defaultComment;
		}

		// Execute the query and fetch results as a numeric index array
		$result = $this->queryAllAsIndexArray($query, $params);

		// Normalize empty result sets to null for easier upstream handling
		return array_merge(...$result) ?: null;
	}

	/**
	 * Return the distinct board UIDs for the given post UIDs.
	 *
	 * @param int[] $postUids Array of post UIDs.
	 * @return array|false Flat array of board UIDs, or false if none found.
	 */
	public function getBoardUidsFromPostUids(array $postUids): false|array {
		// declare base query
		$query = "SELECT boardUID FROM {$this->table}";

		// add where clause
		$placeholders = pdoPlaceholdersForIn($postUids);
		$query .= " WHERE post_uid IN $placeholders";

		// fetch board uids
		$boardUids = $this->queryAllAsIndexArray($query, $postUids);

		if(!$boardUids) {
			return false;
		} else {
			// return result
			return array_merge(...$boardUids);
		}
	}

	/**
	 * Return the host (IP/hostname) for the given post UID.
	 *
	 * @param int $postUid Post UID.
	 * @return string|null Host string, or null if not found.
	 */
	public function resolveHostFromPostUid(int $postUid): ?string {
		$query = "SELECT host FROM {$this->table} WHERE post_uid = :post_uid";
		return $this->queryValue($query, [':post_uid' => $postUid]);
	}
}