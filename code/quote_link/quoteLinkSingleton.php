<?php
/*
* Quote link singleton Kokonotsuba!
* Manages quote links for speedy quote link usage
*/

class quoteLinkSingleton {
	private DatabaseConnection $databaseConnection;
	private static $instance;
	private string $quoteLinkTable;
	private string $postTable;
	private string $threadTable;

	public function __construct($dbSettings){
		$this->databaseConnection = DatabaseConnection::getInstance();

		$this->quoteLinkTable = $dbSettings['QUOTE_LINK_TABLE'];
		$this->postTable = $dbSettings['POST_TABLE'];
		$this->threadTable = $dbSettings['THREAD_TABLE'];
	}

	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			$globalConfig = getGlobalConfig();
			self::$instance = new LoggerInjector(
				new self($dbSettings),
				new LoggerInterceptor(PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'], 'PIOPDO'))
			);
		}
		return self::$instance;
	}

	public static function getInstance() {
		return self::$instance;
	}

	/**
	 * Get quote links joined with posts by an array of post UIDs.
	 * @param int[] $postUids
	 * @return array
	 */
	public function getQuoteLinksByPostUids(array $postUids): array {
		if (empty($postUids)) {
			return [];
		}
	
		// Step 1: Get quoteLinks where target_post_uid is in the array
		$inClause = implode(',', array_map('intval', $postUids));
		$query = "
			SELECT * 
			FROM {$this->quoteLinkTable}
			WHERE target_post_uid IN ($inClause)
		";
	
		/** @var quoteLink[] $quoteLinks */
		$quoteLinks = $this->databaseConnection->fetchAllAsClass($query, [], 'quoteLink');
	
		if (empty($quoteLinks)) {
			return [];
		}
	
		// Step 2: Collect all unique post_uids needed
		$targetPostUids = [];
		$hostPostUids = [];
	
		foreach ($quoteLinks as $ql) {
			$targetPostUids[] = $ql->getTargetPostUid();
			$hostPostUids[] = $ql->getHostPostUid();
		}
	
		$allPostUids = array_unique(array_merge($targetPostUids, $hostPostUids));
		$postInClause = implode(',', array_map('intval', $allPostUids));
	
		// Step 3: Fetch all relevant posts
		$postSql = "
			SELECT * 
			FROM {$this->postTable}
			WHERE post_uid IN ($postInClause)
		";
		$posts = $this->databaseConnection->fetchAllAsArray($postSql);
	
		// Step 4: Index posts by post_uid
		$postMap = [];
		foreach ($posts as $post) {
			$postMap[$post['post_uid']] = $post;
		}
	
		// Step 5: Assemble final result
		$result = [];
		foreach ($quoteLinks as $ql) {
			$targetPost = $postMap[$ql->getTargetPostUid()] ?? null;
			$hostPost   = $postMap[$ql->getHostPostUid()] ?? null;
	
			if ($targetPost && $hostPost) {
				$result[] = [
					[
						'target_post' => $targetPost,
						'host_post'   => $hostPost,
					],
					'quoteLink' => $ql
				];
			}
		}
	
		return $result;
	}	

	public function getQuoteLinksByBoardUid(int $boardUid): array {
		// Step 1: Fetch quoteLink rows for this board
		$quoteLinks = $this->databaseConnection->fetchAllAsClass(
			"SELECT * FROM {$this->quoteLinkTable} WHERE board_uid = :board_uid",
			[':board_uid' => $boardUid],
			'quoteLink'
		);
	
		if (empty($quoteLinks)) {
			return [];
		}
	
		// Step 2: Extract all unique post UIDs (host and target)
		$postUids = [];
		foreach ($quoteLinks as $link) {
			$postUids[] = $link->getTargetPostUid();
			$postUids[] = $link->getHostPostUid();
		}
		$postUids = array_unique($postUids);
	
		// Step 3: Fetch post data using post UIDs
		$postIdList = implode(',', array_map('intval', $postUids));
		$posts = $this->databaseConnection->fetchAllAsArray(
			"SELECT * FROM {$this->postTable} WHERE post_uid IN ($postIdList)"
		);
	
		// Step 4: Index posts by post_uid and gather all thread_uids
		$postMap = [];
		$threadUids = [];
		foreach ($posts as $post) {
			$postMap[$post['post_uid']] = $post;
			if (!empty($post['thread_uid'])) {
				$threadUids[] = $post['thread_uid'];
			}
		}
		$threadUids = array_unique($threadUids);
	
		// Step 5: Fetch thread data using thread_uids with named placeholders
		$placeholders = [];
		$params = [];
		foreach ($threadUids as $i => $uid) {
			$placeholder = ":uid{$i}";
			$placeholders[] = $placeholder;
			$params[$placeholder] = $uid;
		}
	
		$threadQuery = "
			SELECT * FROM {$this->threadTable}
			WHERE thread_uid IN (" . implode(',', $placeholders) . ")
		";
	
		$threads = $this->databaseConnection->fetchAllAsArray($threadQuery, $params);
	
		// Step 6: Index threads by thread_uid
		$threadMap = [];
		foreach ($threads as $thread) {
			$threadMap[$thread['thread_uid']] = $thread;
		}
	
		// Step 7: Assemble result grouped by host_post_uid
		$result = [];
		foreach ($quoteLinks as $link) {
			$hostUid   = $link->getHostPostUid();
			$targetUid = $link->getTargetPostUid();
	
			$hostPost   = $postMap[$hostUid]   ?? null;
			$targetPost = $postMap[$targetUid] ?? null;
	
			if (!$hostPost || !$targetPost) {
				continue;
			}
	
			$hostThread   = $threadMap[$hostPost['thread_uid']]   ?? null;
			$targetThread = $threadMap[$targetPost['thread_uid']] ?? null;
	
			$result[$hostUid][] = [
				'target_post'   => $targetPost,
				'target_thread' => $targetThread,
				'host_post'     => $hostPost,
				'host_thread'   => $hostThread,
				'quoteLink'     => $link,
			];
		}
	
		return $result;
	}	
	
	/**
	 * Insert multiple quote links into the quoteLinkTable.
	 *
	 * @param array $quoteLinks Each item must be an associative array with keys:
	 *                          'host_post_uid', 'target_post_uid', and 'board_uid'
	 * @return int Number of rows inserted
	 */
	public function insertQuoteLinks(array $quoteLinks): int {
		if (empty($quoteLinks)) {
			return 0;
		}
	
		$placeholders = [];
		$params = [];
	
		foreach ($quoteLinks as $link) {
			// Validate and extract values
			if (
				!isset($link['host_post_uid'], $link['target_post_uid'], $link['board_uid']) ||
				!is_numeric($link['host_post_uid']) ||
				!is_numeric($link['target_post_uid']) ||
				!is_numeric($link['board_uid'])
			) {
				continue; // Skip invalid entries
			}
	
			$placeholders[] = "(?, ?, ?)";
			$params[] = (int) $link['board_uid'];
			$params[] = (int) $link['host_post_uid'];
			$params[] = (int) $link['target_post_uid'];
		}
	
		if (empty($placeholders)) {
			return 0;
		}
	
		$query = "
			INSERT INTO {$this->quoteLinkTable} (board_uid, host_post_uid, target_post_uid)
			VALUES " . implode(', ', $placeholders);
	
		return $this->databaseConnection->execute($query, $params);
	}
	
}
