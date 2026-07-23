<?php

namespace Kokonotsuba\thread;

use Exception;
use RuntimeException;
use Kokonotsuba\board\board;
use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\OrderFieldWhitelistTrait;
use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;
use function Kokonotsuba\libraries\openDeletionExistsCondition;
use function Kokonotsuba\libraries\openDeletionJoin;
use function Kokonotsuba\libraries\openFileDeletionExistsCondition;
use function Kokonotsuba\libraries\excludeDeletedPostsCondition;
use function Kokonotsuba\libraries\excludeDeletedThreadsCondition;
use function Kokonotsuba\libraries\bindThreadFilterParameters;
use function Kokonotsuba\libraries\bindBoardUIDFilter;
use function Kokonotsuba\libraries\getBasePostQuery;
use function Kokonotsuba\libraries\mergeMultiplePostRows;
use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for thread records and multi-post queries used to serve board and thread pages. */
class threadRepository extends baseRepository {
	use OrderFieldWhitelistTrait;

	private array $allowedOrderFields;

	public function __construct(
		databaseConnection $databaseConnection, 
		private string $postTable, 
		string $threadTable, 
		private string $threadThemeTable,
		private string $deletedPostsTable,
		private string $fileTable,
		private string $accountTable,
		private string $soudaneTable,
		private string $noteTable,
		private string $countryFlagTable = '',
		private string $displayIpTable = ''
	) {
		parent::__construct($databaseConnection, $threadTable);
		self::validateTableNames($postTable, $threadThemeTable, $deletedPostsTable, $fileTable, $accountTable, $soudaneTable, $noteTable);
		$this->allowedOrderFields = ['last_bump_time', 'last_reply_time', 'thread_created_time', 'post_op_number', 'number_of_posts'];
	}

	/**
	 * Build the base SELECT query for retrieving full thread rows with post counts and join metadata.
	 *
	 * @param bool $includeDeletedCount Whether to include deleted posts in the reply count subquery.
	 * @return string Partial SQL SELECT string.
	 */
	private function getBaseThreadQuery(bool $includeDeletedCount = true): string {
		// attachment-level deletions are independent of whether the OP itself is deleted, so they
		// are tested separately rather than read off the deletion row joined below
		$opAttachmentDeleted = openFileDeletionExistsCondition($this->deletedPostsTable, 't.post_op_post_uid');

		// conditional filter
		$countFilter = $includeDeletedCount
			? ""                    // include all posts
			: ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');

		$query = "
			SELECT
				t.*,

				dp.open_flag AS thread_deleted,
				{$opAttachmentDeleted} AS thread_attachment_deleted,
				dp.by_proxy,

				theme.background_hex_color,
				theme.reply_background_hex_color,
				theme.text_hex_color,
				theme.background_image_url,
				theme.raw_styling,
				theme.audio,
				theme.date_added AS theme_date_added,
				a.username AS theme_added_by,

				(
					SELECT COUNT(*)
					FROM {$this->postTable} p
					WHERE p.thread_uid = t.thread_uid
					{$countFilter}
				) AS number_of_posts

			FROM {$this->table} t
			{$this->getBaseThreadJoinClause()}

			LEFT JOIN $this->threadThemeTable theme ON theme.thread_uid = t.thread_uid
			LEFT JOIN $this->accountTable a ON theme.added_by = a.id
		";

		return $query;
	}

	/**
	 * Build the base COUNT query for threads, including deletion state columns.
	 *
	 * @return string Partial SQL SELECT COUNT string.
	 */
	private function getBaseCountThreadQuery(): string {
		// get join clause
		$joinClause = $this->getBaseThreadJoinClause();
		
		// generate thread count query
		$query = "
			SELECT COUNT(thread_uid),
					t.*,					
					dp.open_flag AS thread_deleted,
					dp.file_only AS thread_attachment_deleted
			FROM {$this->table} t
			$joinClause 
		";

		// return query
		return $query;
	}

	/**
	 * Build the LEFT JOIN clause that attaches the open deletion row for each thread's OP post.
	 *
	 * @return string SQL LEFT JOIN fragment.
	 */
	private function getBaseThreadJoinClause(): string {
		// join clause for thread data
		$joinClause = "\n\t\t\t" . openDeletionJoin($this->deletedPostsTable, 't.post_op_post_uid', 'dp');

		// return join clause
		return $joinClause;
	}

	/**
	 * Fetch a single thread row (with theme and deletion state) by thread UID.
	 *
	 * @param string $thread_uid    Thread UID.
	 * @param bool   $includeDeleted Whether to include the thread if its OP is deleted.
	 * @return Thread|false Thread object, or false if not found.
	 */
	public function getThreadByUid(string $thread_uid, bool $includeDeleted = false): Thread|false {
		// get base thread query
		$query = $this->getBaseThreadQuery($includeDeleted);

		// append WHERE query
		// select thread by `thread_uid`
		$query .= " WHERE t.thread_uid = :thread_uid";

		// exclude deleted threads if needed
		if(!$includeDeleted) {
			$query .= excludeDeletedPostsCondition();
		}

		$params = [':thread_uid' => (string) $thread_uid];
		
		$threadData = $this->databaseConnection->fetchAllAsClass($query, $params, 'Kokonotsuba\\thread\\Thread')[0] ?? null;	
	
		return $threadData ?: false;
	}

	/**
	 * Map an array of thread UIDs to their post_op_number and boardUID.
	 *
	 * @param array $threadUidArray Array of thread UIDs.
	 * @return array Array of rows with 'post_op_number' and 'boardUID'.
	 */
	public function mapThreadUidListToPostNumber(array $threadUidArray) {
		if (empty($threadUidArray)) {
			return array(); // early return if no thread UIDs
		}
	
		$placeholders = pdoPlaceholdersForIn($threadUidArray);
		$query = "SELECT post_op_number, boardUID FROM {$this->table} WHERE thread_uid IN ($placeholders) ORDER BY last_bump_time DESC";
		
		return $this->queryAll($query, $threadUidArray);
	}

	/**
	 * Fetch a paged list of thread UIDs for the given board, excluding deleted threads.
	 *
	 * @param string $boardUID  Board UID.
	 * @param int    $start     Pagination start (LIMIT offset).
	 * @param int    $amount    Number of thread UIDs to return (0 = all).
	 * @param string $orderBy   Column to order by (validated against allowlist).
	 * @param string $direction Sort direction (ASC or DESC).
	 * @return array Flat array of thread_uid strings.
	 */
	public function fetchThreadUIDsByBoard(
		string $boardUID,
		int $start = 0,
		int $amount = 0,
		string $orderBy = 'last_bump_time',
		string $direction = 'DESC'): array {
			
		// Validate orderBy against allowlist
		$orderBy = $this->validateOrderField($orderBy, 'last_bump_time');

		// Validate direction
		$direction = strtoupper($direction);
		if ($direction !== 'ASC' && $direction !== 'DESC') {
			$direction = 'DESC';
		}

		// exclude threads whose OP post is deleted
		$visibleCond = excludeDeletedThreadsCondition($this->deletedPostsTable);

		$query = "SELECT t.thread_uid
				FROM {$this->table} t
				WHERE t.boardUID = :board_uid
					{$visibleCond}
				ORDER BY {$orderBy} {$direction}";

		$params = [':board_uid' => $boardUID];

		if ($amount > 0) {
			$this->paginate($query, $params, $amount, $start);
		}

		$threads = $this->queryAllAsIndexArray($query, $params);
		return !empty($threads) ? array_merge(...$threads) : [];
	}
	
	/**
	 * Insert a new thread row for the given board and OP post.
	 *
	 * @param mixed  $boardUID       Board UID.
	 * @param mixed  $post_uid       OP post UID.
	 * @param mixed  $thread_uid     New thread UID.
	 * @param mixed  $post_op_number OP post number (no).
	 * @return void
	 */
	// insert a new thread into the thread table
	public function addThread($boardUID, $post_uid, $thread_uid, $post_op_number) {
		$this->insert([
			'boardUID' => $boardUID,
			'post_op_post_uid' => $post_uid,
			'post_op_number' => $post_op_number,
			'thread_uid' => $thread_uid,
		]);
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

		$query = "INSERT INTO {$this->table}
			(boardUID, post_op_post_uid, post_op_number, thread_uid)
			VALUES ";

		$values = [];
		$params = [];

		foreach (array_values($threads) as $i => $t) {
			$values[] = "(:board_uid_$i, 0, :post_op_number_$i, :thread_uid_$i)";
			$params[":board_uid_$i"] = $t['board_uid'];
			$params[":post_op_number_$i"] = $t['post_op_number'];
			$params[":thread_uid_$i"] = $t['thread_uid'];
		}

		$query .= implode(',', $values);

		// Execute insert
		$this->query($query, $params);

		// Retrieve the INSERT IDs
		$firstId = $this->lastInsertId();
		$count = count($threads);

		return range($firstId, $firstId + $count - 1);
	}

	/**
	 * Return the creation timestamp of the most recently created thread on the given board.
	 *
	 * @param mixed $board Board object with getBoardUID().
	 * @return string|null Timestamp string, or null if no threads exist.
	 */
	public function getLastThreadTimeFromBoard($board) {
		$boardUID = $board->getBoardUID();
		
		$query = "SELECT MAX(thread_created_time) FROM {$this->table} WHERE boardUID = :boardUID";
		$params = [
			':boardUID' => $boardUID,
		];
		$lastThreadTime = $this->queryColumn($query, $params);
		return $lastThreadTime;
	}

	/**
	 * Return the count of threads, optionally filtered and optionally including deleted threads.
	 *
	 * @param array $filters        Optional filter criteria.
	 * @param bool  $includeDeleted Whether to include threads whose OP is deleted.
	 * @return int Thread count.
	 */
	public function getFilteredThreadCount($filters = [], bool $includeDeleted = false) {
		// get base count query
		$query = $this->getBaseCountThreadQuery();

		// append WHERE clause
		// filter the whole db if not filtered
		$query .= " WHERE 1";

		// exclude the threads where the op post was deleted
		if(!$includeDeleted) {
			$query .= excludeDeletedThreadsCondition($this->deletedPostsTable);
		}

		$params = [];
		
		bindThreadFilterParameters($params, $query, $filters); //apply filtration to query
		
		$threads = $this->queryColumn($query, $params);
	
		return $threads;
	}

	/**
	 * Fetch a filtered, paginated list of full thread rows (with theme and soft-delete state).
	 *
	 * @param array  $filters        Filter criteria (e.g. board UIDs).
	 * @param string $order          Column to order by (validated against allowlist).
	 * @param int    $amount         Number of threads to return.
	 * @param int    $offset         Pagination offset.
	 * @param bool   $includeDeleted Whether to include threads whose OP is deleted.
	 * @return array Array of thread data rows.
	 */
	public function fetchFilteredThreads(array $filters, string $order, int $amount, int $offset, bool $includeDeleted = false): array {
		// Whitelist allowed ORDER BY fields
		$allowedOrderFields = ['thread_uid', 'post_op_number', 'boardUID', 'last_post_time', 'thread_time', 'last_bump_time', 'last_reply_time', 'thread_created_time'];
		if (!in_array($order, $allowedOrderFields, true)) {
			$order = 'last_bump_time';
		}

		// get thread query
		$query = $this->getBaseThreadQuery($includeDeleted);
	
		// apppend WHERE
		$query .= " WHERE 1";

		// exclude the threads where the op post was deleted
		if(!$includeDeleted) {
			$query .= excludeDeletedThreadsCondition($this->deletedPostsTable);
		}

		$params = [];

		if (!empty($filters['board']) && is_array($filters['board'])) {
			bindBoardUIDFilter($params, $query, $filters['board'], 't.boardUID');
		}

		$query .= " ORDER BY t.{$order} DESC";
		$this->paginate($query, $params, $amount, $offset);

		$rows = $this->queryAll($query, $params);
		return array_map(fn($row) => new Thread($row), $rows);
	}

	/**
	 * Check whether a thread with the given UID exists.
	 *
	 * @param mixed $threadID Thread UID to check.
	 * @return bool True if the thread exists.
	 */
	public function isThread($threadID) {
		return $this->exists('thread_uid', strval($threadID));
	}

	/**
	 * Resolve the thread UID from an OP post number (resno) on the given board.
	 *
	 * @param mixed $board Board object with getBoardUID().
	 * @param mixed $resno OP post number.
	 * @return mixed Thread UID, or null/false if not found.
	 */
	public function resolveThreadUidFromResno($board, $resno) {
		$query = "SELECT thread_uid FROM {$this->table} WHERE post_op_number = :resno AND boardUID = :board_uid";
		$params = [
			':resno' => intval($resno),
			':board_uid' => $board->getBoardUID(),
		];
		$thread_uid = $this->queryColumn($query, $params);
		return $thread_uid;
	}

	/**
	 * Resolve the OP post number from a thread UID.
	 *
	 * @param mixed $thread_uid Thread UID.
	 * @return mixed OP post number, or null if not found.
	 */
	public function resolveThreadNumberFromUID($thread_uid) {
		return $this->pluck('post_op_number', 'thread_uid', strval($thread_uid));
	}

	/**
	 * Update both the last bump time and last reply time for a thread.
	 *
	 * @param string $threadUID Thread UID.
	 * @param mixed  $time      New timestamp value.
	 * @return void
	 */
	public function updateThreadBumpAndReplyTime(string $threadUID, $time): void {
		$this->updateWhere(
			['last_bump_time' => $time, 'last_reply_time' => $time],
			'thread_uid', $threadUID
		);
	}

	/**
	 * Permanently delete a thread (and cascade to its posts) by thread UID.
	 *
	 * @param string $threadUID Thread UID to delete.
	 * @return void
	 */
	public function deleteThreadByUID(string $threadUID): void {
		$this->deleteWhere('thread_uid', $threadUID);
	}


	/**
	 * Update only the last reply time of a thread (no bump).
	 *
	 * @param string $threadUID Thread UID.
	 * @param mixed  $time      New timestamp value.
	 * @return void
	 */
	public function updateThreadReplyTime(string $threadUID, $time): void {
		$this->updateWhere(['last_reply_time' => $time], 'thread_uid', $threadUID);
	}

	/**
	 * Fetch the OP (first) post for each thread in the given UID array, indexed by thread_uid.
	 *
	 * @param string[] $threadUIDs Array of thread UIDs.
	 * @return array Map of thread_uid => merged post data array.
	 */
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

		$results = $this->queryAll($query, $threadUIDs);

		// merge rows (in cases of multiple attachments)
		$results = mergeMultiplePostRows($results);

		// Index results by thread_uid for fast lookup
		$indexed = [];
		foreach ($results as $row) {
				$indexed[$row->getThreadUid()] = $row;
		}

		return $indexed;
	}

	/**
	 * Return the OP post UIDs (post_op_post_uid) for an array of thread UIDs.
	 *
	 * @param string[] $threadUIDs Array of thread UIDs.
	 * @return int[] Flat array of OP post UIDs.
	 */
	public function getOpPostUidsFromThreads(array $threadUIDs): array {
		if (empty($threadUIDs)) {
				return [];
		}

		return $this->pluckWhereIn('post_op_post_uid', 'thread_uid', $threadUIDs);
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
			UPDATE {$this->table}
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

		$this->query($query, $params);
	}

	/**
	 * Fetch threads for a board with full post-count metadata.
	 *
	 * @param int    $boardUid       Board UID.
	 * @param int    $limit          Maximum number of threads to return.
	 * @param int    $offset         Pagination offset.
	 * @param string $orderBy        Column to sort by (validated against allowlist).
	 * @param string $direction      Sort direction (ASC or DESC).
	 * @param bool   $includeDeleted Whether to include threads whose OP is deleted.
	 * @return array|null Array of thread data rows, or null if none found.
	 */
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
		if (!$this->isValidOrderField($orderBy)) {
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

		// optionally exclude threads where the OP post itself is deleted. thread_deleted already
		// means "open post-level deletion", which excludes attachment-level ones by construction,
		// so a thread that merely lost a file is still visible without testing for it separately
		if (!$includeDeleted) {
			$query .= " AND COALESCE(t.thread_deleted, 0) = 0";
		}

		// add ordering (sticky first)
		$query .= " ORDER BY t.is_sticky DESC, t.{$orderBy} {$direction}";

		// add param
		$params = [':board_uid' => $boardUid];

		// add limits
		if ($limit) {
			$this->paginate($query, $params, $limit, $offset);
		}

		// return db query fetching the thread data
		$rows = $this->queryAll($query, $params);
		return $rows ? array_map(fn($row) => new Thread($row), $rows) : null;
	}


	/**
	 * Fetch posts from a single thread, with the OP always included and replies paginated.
	 *
	 * @param string $threadUID      Thread UID.
	 * @param bool   $includeDeleted Whether to include soft-deleted posts.
	 * @param int    $amount         Max number of reply posts to return (0 = all).
	 * @param int    $offset         Reply pagination offset.
	 * @return array|null Array of merged post data arrays, or null if none found.
	 */
	public function getPostsFromThread(string $threadUID, bool $includeDeleted = false, int $amount = 500, int $offset = 0): ?array {
		$amount = max(0, (int)$amount);
		$offset = max(0, (int)$offset);

		// Step 1: lightweight query — fetch only the post UIDs we need
		$postUIDs = $this->fetchPostUIDsForThread($threadUID, $includeDeleted, $amount, $offset);

		if (empty($postUIDs)) {
			return null;
		}

		// Step 2: single full query for those UIDs
		$placeholders = pdoPlaceholdersForIn($postUIDs);
		$query = getBasePostQuery(
			$this->postTable, $this->deletedPostsTable, $this->fileTable, $this->table,
			$this->soudaneTable, $this->noteTable, $this->accountTable,
			$includeDeleted, false, $this->countryFlagTable, $this->displayIpTable,
			includeObjectivePosition: false
		);
		$query .= " WHERE p.post_uid IN {$placeholders} ORDER BY p.post_uid ASC";

		$posts = $this->queryAll($query, $postUIDs) ?? [];
		$posts = mergeMultiplePostRows($posts);

		if ($posts) {
			$this->attachObjectivePositions($posts, $includeDeleted);
		}

		return $posts ?: null;
	}

	/**
	 * Eager-load each post's objective position and set it on the Post objects.
	 *
	 * The objective position is a post's true ordinal within its thread (OP = 0, replies
	 * numbered by their order among the currently-visible replies) - the position that
	 * pagination actually slices on, as opposed to the drift-prone stored post_position
	 * column. A single window-function query covers every thread present in the batch, so
	 * there is no per-post lookup even when rendering many threads (e.g. the board index).
	 *
	 * @param Post[] $posts          Posts to annotate (mutated in place).
	 * @param bool   $includeDeleted Whether deleted replies count toward the ordinal; must
	 *                               match the visibility the posts were fetched with.
	 */
	private function attachObjectivePositions(array $posts, bool $includeDeleted): void {
		if (empty($posts)) {
			return;
		}

		$threadUIDs = array_values(array_unique(array_map(
			static fn(Post $post): string => $post->getThreadUid(),
			$posts
		)));

		$postUIDs = array_values(array_map(
			static fn(Post $post): int => $post->getUid(),
			$posts
		));

		$positions = $this->getObjectivePositions($threadUIDs, $includeDeleted, $postUIDs);

		foreach ($posts as $post) {
			$post->setObjectivePosition($positions[$post->getUid()] ?? 0);
		}
	}

	/**
	 * Map each post_uid in the given threads to its objective position (OP = 0, replies
	 * numbered 1..N in post_uid order among the currently-visible posts). One
	 * window-function query for the whole set of threads.
	 *
	 * @param string[] $threadUIDs     Thread UIDs to compute positions for.
	 * @param bool     $includeDeleted Whether deleted replies count toward the ordinal.
	 * @param string[] $restrictToPostUIDs Return only these posts' positions. The ordinals are
	 *                                 unchanged - see below - so this is purely a way to avoid
	 *                                 hauling back a whole thread to position a preview.
	 * @return array<int,int> post_uid => objective position
	 */
	public function getObjectivePositions(array $threadUIDs, bool $includeDeleted = false, array $restrictToPostUIDs = []): array {
		if (empty($threadUIDs)) {
			return [];
		}

		$placeholders = pdoPlaceholdersForIn($threadUIDs);

		// Same latest-post-level-deletion filter pagination uses, so the ordinal matches
		// the set of replies actually rendered.
		$deletionFilter = '';
		if (!$includeDeleted) {
			$deletionFilter = ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');
		}

		// OP first (is_op DESC) then replies by post_uid ASC — the exact order pagination
		// slices on. Subtracting 1 places the OP at position 0.
		$query = "
			SELECT
				p.post_uid,
				ROW_NUMBER() OVER (
					PARTITION BY p.thread_uid
					ORDER BY p.is_op DESC, p.post_uid ASC
				) - 1 AS objective_position
			FROM {$this->postTable} p
			WHERE p.thread_uid IN {$placeholders}
			{$deletionFilter}
		";

		$params = $threadUIDs;

		// ROW_NUMBER has to see every post in the partition to number correctly, so the restriction
		// is applied *outside* the window rather than in its WHERE - the ordinals are identical
		// either way. Callers rendering a handful of preview posts would otherwise get back a row
		// for every post in every thread on the page, only to index and discard almost all of them.
		if (!empty($restrictToPostUIDs)) {
			$restrictPlaceholders = pdoPlaceholdersForIn($restrictToPostUIDs);
			$query = "
				SELECT positioned.post_uid, positioned.objective_position
				FROM (
					$query
				) positioned
				WHERE positioned.post_uid IN {$restrictPlaceholders}
			";
			$params = array_merge($params, array_values($restrictToPostUIDs));
		}

		$rows = $this->queryAll($query, $params);

		$map = [];
		foreach ($rows ?? [] as $row) {
			$map[(int)$row['post_uid']] = (int)$row['objective_position'];
		}

		return $map;
	}

	/**
	 * Fetch the post UIDs needed for getPostsFromThread: always the OP, plus paginated replies.
	 */
	private function fetchPostUIDsForThread(string $threadUID, bool $includeDeleted, int $amount, int $offset): array {
		$deletionFilter      = '';
		$threadDeletionFilter = '';

		if (!$includeDeleted) {
			$deletionFilter = ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');
			$threadDeletionFilter = excludeDeletedThreadsCondition($this->deletedPostsTable);
		}

		$baseFragment = "
			FROM {$this->postTable} p
			INNER JOIN {$this->table} t ON t.thread_uid = p.thread_uid
			WHERE 1=1
			{$deletionFilter}
			{$threadDeletionFilter}";

		$query = "
			(SELECT p.post_uid {$baseFragment} AND p.thread_uid = :thread_uid   AND p.is_op = 1)
			UNION ALL
			(SELECT p.post_uid {$baseFragment} AND p.thread_uid = :thread_uid_2 AND p.is_op = 0
			 ORDER BY p.post_uid ASC
			 LIMIT :_limit OFFSET :_offset)
		";

		$params = [
			':thread_uid'   => $threadUID,
			':thread_uid_2' => $threadUID,
			':_limit'       => $amount,
			':_offset'      => $offset,
		];

		$rows = $this->queryAll($query, $params);
		return $rows ? array_column($rows, 'post_uid') : [];
	}

	/**
	 * Fetch thread replies newer than a given post UID, oldest-first.
	 *
	 * Used by the incremental thread updater: the client sends the UID of the newest
	 * post it already has, and only genuinely new replies are returned. The OP is never
	 * included (it's always already on the page). Returns an empty array — not null —
	 * when the thread exists but has no newer replies, so callers can distinguish
	 * "nothing new" from "thread gone" with a separate existence check.
	 *
	 * @param string $threadUID      Thread UID.
	 * @param int    $afterPostUid   Only return replies with post_uid greater than this.
	 * @param bool   $includeDeleted Whether to include soft-deleted posts.
	 * @param int    $amount         Safety cap on how many replies to return at once.
	 * @return Post[] Array of Post objects (possibly empty).
	 */
	public function getRepliesFromThreadAfterUid(string $threadUID, int $afterPostUid, bool $includeDeleted = false, int $amount = 500): array {
		$amount       = max(0, (int)$amount);
		$afterPostUid = max(0, (int)$afterPostUid);

		$postUIDs = $this->fetchReplyUIDsAfter($threadUID, $afterPostUid, $includeDeleted, $amount);

		if (empty($postUIDs)) {
			return [];
		}

		$placeholders = pdoPlaceholdersForIn($postUIDs);
		$query = getBasePostQuery(
			$this->postTable, $this->deletedPostsTable, $this->fileTable, $this->table,
			$this->soudaneTable, $this->noteTable, $this->accountTable,
			$includeDeleted, false, $this->countryFlagTable, $this->displayIpTable,
			includeObjectivePosition: false
		);
		$query .= " WHERE p.post_uid IN {$placeholders} ORDER BY p.post_uid ASC";

		$posts = $this->queryAll($query, $postUIDs) ?? [];
		$posts = mergeMultiplePostRows($posts);

		if ($posts) {
			$this->attachObjectivePositions($posts, $includeDeleted);
		}

		return $posts ?: [];
	}

	/**
	 * Fetch the UIDs of replies newer than a given post UID (for incremental updates).
	 */
	private function fetchReplyUIDsAfter(string $threadUID, int $afterPostUid, bool $includeDeleted, int $amount): array {
		$deletionFilter       = '';
		$threadDeletionFilter = '';

		if (!$includeDeleted) {
			$deletionFilter = ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');
			$threadDeletionFilter = excludeDeletedThreadsCondition($this->deletedPostsTable);
		}

		$query = "
			SELECT p.post_uid
			FROM {$this->postTable} p
			INNER JOIN {$this->table} t ON t.thread_uid = p.thread_uid
			WHERE p.thread_uid = :thread_uid
			  AND p.is_op = 0
			  AND p.post_uid > :after_uid
			  {$deletionFilter}
			  {$threadDeletionFilter}
			ORDER BY p.post_uid ASC
			LIMIT :_limit";

		$params = [
			':thread_uid' => $threadUID,
			':after_uid'  => $afterPostUid,
			':_limit'     => $amount,
		];

		$rows = $this->queryAll($query, $params);
		return $rows ? array_column($rows, 'post_uid') : [];
	}

	/**
	 * Fetch all posts from a thread without pagination.
	 *
	 * @param string $threadUID      Thread UID.
	 * @param bool   $includeDeleted Whether to include soft-deleted posts.
	 * @return Post[]|null Array of Post objects, or null if none found.
	 */
	public function getAllPostsFromThread(string $threadUID, bool $includeDeleted = false): ?array {
		// Generate the base query
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->table, $this->soudaneTable, $this->noteTable, $this->accountTable,  $includeDeleted, false, $this->countryFlagTable, $this->displayIpTable, includeObjectivePosition: false);

		// Add thread condition
		$query .= " WHERE p.thread_uid = :thread_uid";

		// Exclude deleted posts if requested
		if(!$includeDeleted) {
			$query .= excludeDeletedThreadsCondition($this->deletedPostsTable);
		}

		// Order results by post id
		$query .= " ORDER BY p.post_uid ASC";

		$params = [
			':thread_uid' => $threadUID
		];

		// Fetch rows
		$posts = $this->queryAll($query, $params) ?? [];

		// Merge attachment rows
		$posts = mergeMultiplePostRows($posts);

		// Return null when empty
		if(!$posts) {
			return null;
		}

		$this->attachObjectivePositions($posts, $includeDeleted);

		// Return all posts
		return $posts;
	}

	/**
	 * Fetch all non-deleted posts from a thread, even if the thread's OP is deleted.
	 * This allows retrieving visible replies from a deleted thread.
	 *
	 * @param string $threadUID Thread UID.
	 * @return Post[]|null Array of Post objects, or null if none found.
	 */
	public function getVisiblePostsFromDeletedThread(string $threadUID): ?array {
		// viewDeleted=false filters out deleted posts in the subquery
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->table, $this->soudaneTable, $this->noteTable, $this->accountTable, false, false, $this->countryFlagTable, $this->displayIpTable, includeObjectivePosition: false);

		// Filter by thread — intentionally no excludeDeletedThreadsCondition
		// so the query works even when the OP is deleted
		$query .= " WHERE p.thread_uid = :thread_uid";
		$query .= " ORDER BY p.post_uid ASC";

		$params = [':thread_uid' => $threadUID];

		$posts = $this->queryAll($query, $params) ?? [];
		$posts = mergeMultiplePostRows($posts);

		if ($posts) {
			// deleted-thread view still hides post-level-deleted replies (viewDeleted=false)
			$this->attachObjectivePositions($posts, false);
		}

		return $posts ?: null;
	}

	/**
	 * Fetch replies from a thread with post number greater than a given value.
	 * Excludes the OP. Used for incremental reply loading.
	 *
	 * @param string $threadUID      Thread UID.
	 * @param int    $afterPostNo    Only return replies with no > this value.
	 * @param bool   $includeDeleted Whether to include soft-deleted posts.
	 * @return Post[]|null Array of Post objects, or null if none found.
	 */
	public function getRepliesAfterPostNumber(string $threadUID, int $afterPostNo, bool $includeDeleted = false): ?array {
		$query = getBasePostQuery($this->postTable, $this->deletedPostsTable, $this->fileTable, $this->table, $this->soudaneTable, $this->noteTable, $this->accountTable, $includeDeleted, false, $this->countryFlagTable, $this->displayIpTable, includeObjectivePosition: false);

		$query .= " WHERE p.thread_uid = :thread_uid AND p.is_op = 0 AND p.no > :after_no";

		if (!$includeDeleted) {
			$query .= excludeDeletedThreadsCondition($this->deletedPostsTable);
		}

		$query .= " ORDER BY p.post_uid ASC";

		$params = [
			':thread_uid' => $threadUID,
			':after_no' => $afterPostNo,
		];

		$posts = $this->queryAll($query, $params) ?? [];
		$posts = mergeMultiplePostRows($posts);

		if ($posts) {
			$this->attachObjectivePositions($posts, $includeDeleted);
		}

		return $posts ?: null;
	}

	/**
	 * Fetch the latest N posts from each of the given threads.
	 * Results always include the OP and up to ($previewCount - 1) most-recent replies.
	 *
	 * Takes Thread objects rather than bare UIDs because each thread's number_of_posts is what
	 * makes the posts' objective positions derivable without re-reading the thread - see
	 * deriveObjectivePositions(). Those counts must therefore come from a thread query run with
	 * the same $includeDeleted visibility as this call, which is how all callers use it.
	 *
	 * @param Thread[] $threads       Threads to fetch previews for.
	 * @param int      $previewCount  Number of posts to return per thread (including OP).
	 * @param bool     $includeDeleted Whether to include soft-deleted posts.
	 * @return array Array of merged post data arrays across all threads.
	 */
	public function getPostsForThreads(array $threads, int $previewCount, bool $includeDeleted = false): array {
		if (empty($threads)) return [];

		// include OP
		$previewCount++;

		// excludeDeletedThreadsCondition() tests for an open post-level deletion on the thread's
		// OP, which is exactly what thread_deleted already records on the row in hand - so the
		// thread-level filter is applied here rather than joining the deleted-posts table into
		// every branch of the query below.
		// $threadUIDs is kept alongside the map rather than recovered from its keys: thread_uid is a
		// VARCHAR, but PHP silently turns a numeric-string array key into an int, and bindTypedParams()
		// then binds it as PDO::PARAM_INT. Comparing a varchar column against an integer makes MariaDB
		// convert the column instead of probing idx_posts_thread_rank, turning a 45-row ref lookup
		// into a full scan with a filesort. Hold on to the strings.
		$threadUIDs = [];
		$visiblePostCounts = [];
		foreach ($threads as $thread) {
			if (!$includeDeleted && $thread->isThreadDeleted()) {
				continue;
			}
			$threadUID = $thread->getUid();
			$threadUIDs[] = $threadUID;
			$visiblePostCounts[$threadUID] = $thread->getPostCount();
		}

		if (empty($threadUIDs)) {
			return [];
		}

		// Step 1: read just the preview posts. This was once joined to the full post query below as
		// two derived tables, but that query carries no restriction of its own and its inner derived
		// tables stop the optimiser pushing "rn <= N" down into it, so the database built every post
		// on the site and then threw all but the preview away. Resolving the UIDs first and passing
		// them as an IN list keeps the expensive query bounded to the rows actually rendered.
		$previewRows = $this->fetchPreviewPostRows($threadUIDs, $previewCount, $includeDeleted);

		if (empty($previewRows)) {
			return [];
		}

		$positions = $this->deriveObjectivePositions($previewRows, $visiblePostCounts);
		$postUIDs = array_keys($positions);

		// Step 2: single full query for exactly those UIDs.
		$placeholders = pdoPlaceholdersForIn($postUIDs);
		$query = getBasePostQuery(
			$this->postTable, $this->deletedPostsTable, $this->fileTable, $this->table,
			$this->soudaneTable, $this->noteTable, $this->accountTable,
			$includeDeleted, false, $this->countryFlagTable, $this->displayIpTable,
			includeObjectivePosition: false
		);
		$query .= " WHERE p.post_uid IN {$placeholders} ORDER BY p.no ASC";

		$posts = $this->queryAll($query, $postUIDs) ?? [];
		$posts = mergeMultiplePostRows($posts);

		// Step 1 already knows each post's ordinal, so there is no attachObjectivePositions() call
		// here - that would re-scan the same threads a second time to recompute what we have.
		foreach ($posts as $post) {
			$post->setObjectivePosition($positions[$post->getUid()] ?? 0);
		}

		return $posts ?: [];
	}

	/**
	 * Read the preview posts for each thread: the OP, then the most recent replies, capped per
	 * thread.
	 *
	 * One branch per thread rather than a single ROW_NUMBER() over all of them. The window form
	 * has to materialise and rank every visible post in every thread on the page before the outer
	 * "rn <= N" throws almost all of them away; a per-thread ORDER BY ... LIMIT walks
	 * idx_posts_thread_rank (thread_uid, is_op DESC, post_uid DESC) backwards and stops after N
	 * rows. Same rows, same order, but the work is bounded by what is rendered instead of by how
	 * long the threads happen to be.
	 *
	 * @param string[] $threadUIDs     Thread UIDs to read, already filtered for thread-level deletion.
	 * @param int      $rankLimit      Posts to keep per thread, OP included.
	 * @param bool     $includeDeleted Whether soft-deleted posts are returned.
	 * @return array Rows of post_uid, thread_uid, is_op.
	 */
	private function fetchPreviewPostRows(array $threadUIDs, int $rankLimit, bool $includeDeleted): array {
		$deletionFilter = '';
		if (!$includeDeleted) {
			$deletionFilter = ' AND NOT ' . openDeletionExistsCondition($this->deletedPostsTable, 'p.post_uid');
		}

		// Interpolated, not bound: this is a validated integer rather than caller data, and a named
		// placeholder cannot be reused across branches without relying on emulated prepares.
		$rankLimit = max(0, $rankLimit);

		$branches = [];
		$params   = [];

		foreach ($threadUIDs as $threadUID) {
			$branches[] = "
				(SELECT p.post_uid, p.thread_uid, p.is_op
				FROM {$this->postTable} p
				WHERE p.thread_uid = ?
				{$deletionFilter}
				ORDER BY p.is_op DESC, p.post_uid DESC
				LIMIT {$rankLimit})
			";
			$params[] = $threadUID;
		}

		return $this->queryAll(implode("\nUNION ALL\n", $branches), $params) ?? [];
	}

	/**
	 * Work out each preview post's objective position without going back to the database.
	 *
	 * The position is a post's ordinal among its thread's currently-visible posts, OP = 0. Ranking
	 * the posts to find that out means reading the whole thread, but the same ordinal falls out of
	 * arithmetic: for a thread of N visible posts of which O are the OP (one, or none once the OP
	 * itself is deleted), a reply lying D places from the newest end sits at position N + O - D.
	 * N is the thread row's number_of_posts, counted with the very same visibility filter, so the
	 * preview posts can be positioned from the handful of rows already in hand.
	 *
	 * Rows are re-sorted here rather than trusted to arrive in order: UNION ALL gives no guarantee
	 * that a branch's ORDER BY survives into the combined result.
	 *
	 * @param array          $previewRows       Rows from fetchPreviewPostRows().
	 * @param array<int|string,int> $visiblePostCounts thread_uid => number_of_posts.
	 * @return array<int,int> post_uid => objective position.
	 */
	private function deriveObjectivePositions(array $previewRows, array $visiblePostCounts): array {
		$byThread = [];
		foreach ($previewRows as $row) {
			$byThread[$row['thread_uid']][] = $row;
		}

		$positions = [];

		foreach ($byThread as $threadUID => $rows) {
			// OP first, then newest reply downwards - the order the ranking used.
			usort($rows, static fn(array $a, array $b): int =>
				[(int)$b['is_op'], (int)$b['post_uid']] <=> [(int)$a['is_op'], (int)$a['post_uid']]);

			// A thread absent from the counts cannot be positioned by arithmetic; falling back to
			// the rows in hand keeps the OP at 0 rather than inventing an offset.
			$visibleCount = $visiblePostCounts[$threadUID] ?? count($rows);
			$hasOp        = (int)($rows[0]['is_op'] ?? 0) === 1 ? 1 : 0;

			foreach ($rows as $index => $row) {
				$positions[(int)$row['post_uid']] = (int)$row['is_op'] === 1
					? 0
					: $visibleCount + $hasOp - ($index + 1);
			}
		}

		return $positions;
	}

	/**
	 * Return the thread count for the given board.
	 *
	 * @param board $board          Board object.
	 * @param bool  $includeDeleted Whether to count threads whose OP is deleted.
	 * @return int Thread count.
	 */
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
			$query .= excludeDeletedThreadsCondition($this->deletedPostsTable);
		}
		
		// params
		$params = [
			':board_uid' => $boardUid,
		];

		// fetch column from db
		$threadCount = $this->queryValue($query, $params);

		// return threadCount
		return $threadCount;
	}

	/**
	 * Return the reply count for a single thread.
	 *
	 * @param mixed $threadUID Thread UID.
	 * @return int Reply count.
	 * @throws Exception If $threadUID is empty.
	 */
	public function getPostCountFromThread($threadUID) {
		if(!$threadUID) throw new Exception("Invalid thread UID in ".__METHOD__);
		$query = "SELECT COUNT(post_uid) FROM {$this->postTable} WHERE thread_uid = ?";
		$count = $this->queryColumn($query, [$threadUID]);
		return $count;
	}

	/**
	 * Update the last reply time of a thread to the current timestamp.
	 *
	 * @param mixed $threadID Thread UID.
	 * @return void
	 */
	public function updateThreadLastReplyTime($threadID) {
		$query = "UPDATE {$this->table} SET last_reply_time = CURRENT_TIMESTAMP WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $threadID
		]; 
	
		$this->query($query, $params);
	}

	/**
	 * Insert a thread row with a placeholder OP post UID of -1 (to be updated after post insertion).
	 *
	 * @param mixed $threadUid    Thread UID.
	 * @param mixed $postOpNumber OP post number.
	 * @param mixed $boardUID     Board UID.
	 * @return void
	 */
	public function insertThread($threadUid, $postOpNumber, $boardUID) {
		$this->insert([
			'thread_uid' => $threadUid,
			'post_op_number' => $postOpNumber,
			'post_op_post_uid' => -1,
			'boardUID' => $boardUID,
		]);
	}
	
	/**
	 * Update the post_op_post_uid for the given thread UID.
	 *
	 * @param string $threadUid   Thread UID.
	 * @param string $postOpUid   New OP post UID.
	 * @return void
	 */
	public function updateThreadOpPostUid(string $threadUid, string $postOpUid): void {
		$this->updateWhere(['post_op_post_uid' => $postOpUid], 'thread_uid', $threadUid);
	}

	/**
	 * Update the post number, board UID, and comment content of a post when it is moved to another board.
	 *
	 * @param mixed  $postUid      Post UID.
	 * @param mixed  $newPostNumber New post number in destination board.
	 * @param mixed  $newBoardUID  Destination board UID.
	 * @param mixed  $updatedCom   Updated comment with remapped quote links.
	 * @return void
	 */
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
		$this->query($query, $params);
	}

	/**
	 * Update the board UID and OP post number of a thread when it is moved to another board.
	 *
	 * @param mixed $threadUid         Thread UID.
	 * @param mixed $newBoardUID       Destination board UID.
	 * @param mixed $newPostOpNumber   New OP post number in destination board.
	 * @return void
	 */
	public function updateThreadForBoardMove($threadUid, $newBoardUID, $newPostOpNumber): void {
		$this->updateWhere(
			['boardUID' => intval($newBoardUID), 'post_op_number' => intval($newPostOpNumber)],
			'thread_uid', $threadUid
		);
	}

	/**
	 * Set the is_sticky flag to true on the given thread.
	 *
	 * @param string $thread_uid Thread UID.
	 * @return void
	 */
	public function stickyThread(string $thread_uid): void {
		$this->updateWhere(['is_sticky' => true], 'thread_uid', $thread_uid);
	}

	/**
	 * Clear the is_sticky flag on the given thread.
	 *
	 * @param string $thread_uid Thread UID.
	 * @return void
	 */
	public function unstickyThread(string $thread_uid): void {
		$this->updateWhere(['is_sticky' => false], 'thread_uid', $thread_uid);
	}

	/**
	 * Update the post_op_post_uid in the thread table for a given thread.
	 *
	 * @param string $threadUid The unique identifier for the thread
	 * @param int $postOpPostUid The post UID for the thread OP (original post)
	 * @return void
	 */
	public function updatePostOpUid(string $threadUid, int $postOpPostUid): void {
		$this->updateWhere(['post_op_post_uid' => $postOpPostUid], 'thread_uid', $threadUid);
	}

	/**
	 * Return the 1-based page number that the given thread appears on.
	 *
	 * @param string $threadUid      Thread UID.
	 * @param int    $threadsPerPage Number of threads per page.
	 * @return int|null|false Page number, or null/false if not found.
	 */
	public function getPageOfThread(string $threadUid, int $threadsPerPage): null|false|int {
		// get the query to get the page of the thread
		$query = "
			SELECT CEIL(
				(
					SELECT COUNT(*)
					FROM {$this->table} t2
					WHERE t2.last_bump_time <= t1.last_bump_time
				) / :threads_per_page
			) AS page
			FROM {$this->table} t1
			WHERE t1.thread_uid = :thread_uid
		";

		// bind param
		$params = [
			':thread_uid' => $threadUid,
			':threads_per_page' => $threadsPerPage,
		];

		// fetch value
		$threadPage = $this->queryValue($query, $params);

		// return it
		return $threadPage;
	}
}