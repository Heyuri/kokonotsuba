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
	private array $allowedOrderFields;

	public function __construct($dbSettings){
		$this->databaseConnection = DatabaseConnection::getInstance();

		$this->quoteLinkTable = $dbSettings['QUOTE_LINK_TABLE'];
		$this->postTable = $dbSettings['POST_TABLE'];
		$this->allowedOrderFields = ['quotelink_id'];
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
		// Step 1: Fetch quoteLink rows for the board
		$query = "
			SELECT * 
			FROM {$this->quoteLinkTable}
			WHERE board_uid = :board_uid
		";
	
		$params = [':board_uid' => $boardUid];
	
		/** @var quoteLink[] $quoteLinks */
		$quoteLinks = $this->databaseConnection->fetchAllAsClass($query, $params, 'quoteLink');
	
		if (empty($quoteLinks)) {
			return [];
		}
	
		// Step 2: Collect unique post UIDs involved
		$postUids = [];
		foreach ($quoteLinks as $ql) {
			$postUids[] = $ql->getTargetPostUid();
			$postUids[] = $ql->getHostPostUid();
		}
		$postUids = array_unique($postUids);
	
		// Step 3: Fetch post data
		$inClause = implode(',', array_map('intval', $postUids));
		$postSql = "
			SELECT * 
			FROM {$this->postTable}
			WHERE post_uid IN ($inClause)
		";
		$posts = $this->databaseConnection->fetchAllAsArray($postSql);
	
		// Step 4: Index posts by post_uid
		$postMap = [];
		foreach ($posts as $post) {
			$postMap[$post['post_uid']] = $post;
		}
	
		// Step 5: Assemble result with host_post_uid as key (grouped)
		$result = [];
		foreach ($quoteLinks as $ql) {
			$hostPostUid = $ql->getHostPostUid();
			$targetPost = $postMap[$ql->getTargetPostUid()] ?? null;
			$hostPost   = $postMap[$hostPostUid] ?? null;
	
			if ($targetPost && $hostPost) {
				$result[$hostPostUid][] = [
					'target_post' => $targetPost,
					'host_post'   => $hostPost,
					'quoteLink'   => $ql,
				];
			}
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
