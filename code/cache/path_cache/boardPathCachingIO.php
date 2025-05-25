<?php
//singleton to interface with board path objects
class boardPathCachingIO {
	private static $instance = null;
	private $tablename, $databaseConnection;

	// Static cache for per-request query results
	private static array $pathCache = [];

	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			self::$instance = new self($dbSettings);
		}
		return self::$instance;
	}

	public static function getInstance() {
		return self::$instance;
	}

	public function __wakeup() {
		throw new Exception("Unserialization is not allowed.");
	}

	private function __clone() {}

	private function __construct($dbSettings) {
		$this->tablename = $dbSettings['BOARD_PATH_CACHE_TABLE'];
		$this->databaseConnection = DatabaseConnection::getInstance();
	}

	// Internal caching helper
	private function cacheResult(string $key, callable $fn) {
		if (isset(self::$pathCache[$key])) {
			return self::$pathCache[$key];
		}
		return self::$pathCache[$key] = $fn();
	}

	// Clear cache on write operations
	private function invalidatePathCache(): void {
		self::$pathCache = [];
	}

	public function getRowByID($id) {
		$cacheKey = __METHOD__ . ':' . intval($id);

		return $this->cacheResult($cacheKey, function () use ($id) {
			$query = "SELECT * FROM {$this->tablename} WHERE id = ?";
			$row = $this->databaseConnection->fetchAsClass($query, [$id], 'cachedBoardPath');
			return $row ?? false;
		});
	}

	public function deleteRowByID($id) {
		$query = "DELETE FROM {$this->tablename} WHERE id = ?";
		$this->databaseConnection->execute($query, [$id]);
		$this->invalidatePathCache();
	}

	public function getRowByBoardUID($uid) {
		$cacheKey = __METHOD__ . ':' . intval($uid);

		return $this->cacheResult($cacheKey, function () use ($uid) {
			$query = "SELECT * FROM {$this->tablename} WHERE boardUID = ?";
			$row = $this->databaseConnection->fetchAsClass($query, [$uid], 'cachedBoardPath');
			return $row ?? false;
		});
	}

	public function getAllRows() {
		return $this->cacheResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->tablename}";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'cachedBoardPath');
		});
	}

	public function updateBoardPathCacheByBoardUID($board_uid, $board_path) {
		$query = "UPDATE {$this->tablename} SET board_path = :board_path WHERE boardUID = :boardUID";
		$params = [
			':board_path' => strval($board_path),
			':boardUID' => intval($board_uid),
		];

		$this->databaseConnection->execute($query, $params);
		$this->invalidatePathCache();
	}

	public function addNewCachedBoardPath($board_uid, $board_path) {
		$query = "INSERT INTO {$this->tablename} (boardUID, board_path) VALUES(:boardUID, :board_path)";
		$params = [
			':boardUID' => intval($board_uid),
			':board_path' => strval($board_path),
		];
		$this->databaseConnection->execute($query, $params);
		$this->invalidatePathCache();
	}
}