<?php
/*
* Thread caching singleton for Kokonotsuba!
* Manages in-thread and thread preview (board index) caches
*/

class threadCacheSingleton {
	private DatabaseConnection $databaseConnection;
	private static $instance;
	private string $threadCacheTable;
	private array $allowedOrderFields;

	public function __construct($dbSettings){
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
	
		$this->threadCacheTable = $dbSettings['THREAD_CACHE_TABLE'];

		$this->allowedOrderFields = ['cache_id'];
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

	public function getAllThreadCachesFromBoard(int $boardUid, string $order = 'cache_id'): array {
		if(!in_array($order, $this->allowedOrderFields)) return [];

		$query = "SELECT * FROM {$this->threadCacheTable} WHERE board_uid = :board_uid ORDER BY $order";
		$params = [
			':board_uid' => $boardUid
		];

		return $this->databaseConnection->fetchAllAsClass($query, $params, 'threadCache') ?? [];
	}

	public function getThreadCacheByThreadUid(string $thread_uid) {
		$query = "SELECT * FROM {$this->threadCacheTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $thread_uid
		];

		$this->databaseConnection->fetchAsClass($query, $params, 'threadCache');
	}

	public function deleteThreadCacheByThreadUid(string $thread_uid): void {
		$query = "DELETE FROM {$this->threadCacheTable} WHERE thread_uid = :thread_uid";
		$params = [
			':thread_uid' => $thread_uid
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function updateThreadCache(threadCache $threadCache): void {
		$cache_id = $threadCache->getCacheId();
		$thread_uid = $threadCache->getThreadUid();
		$board_uid = $threadCache->getBoardUid();
		$thread_html = $threadCache->getThreadHtml();
		$thread_index_html = $threadCache->getThreadIndexHtml();

		$query = "UPDATE {$this->threadCacheTable} SET thread_uid = :thread_uid, board_uid = :board_uid, 
                thread_html = :thread_html, thread_index_html = :thread_index_html
            	WHERE cache_id = :cache_id";
		$params = [
			':cache_id' => $cache_id,
			':thread_uid' => $thread_uid,
			':board_uid' => $board_uid,
			':thread_html' => $thread_html,
			':thread_index_html' => $thread_index_html,
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function insertThreadCache(int $board_uid, string $thread_uid, string $thread_html, string $thread_index_html): void {
		$query = "INSERT INTO {$this->threadCacheTable} (board_uid, thread_uid, thread_html, thread_index_html) VALUES (:board_uid, :thread_uid, :thread_html, :thread_index_html)";
		
		// Prepare the parameters to be bound to the query
		$params = [
			':board_uid' => $board_uid,
			':thread_uid' => $thread_uid,
			':thread_html' => $thread_html,
			':thread_index_html' => $thread_index_html,
		];
	
		// Execute the query with the parameters
		$this->databaseConnection->execute($query, $params);
	}
	
}