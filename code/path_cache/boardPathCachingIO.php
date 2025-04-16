<?php
//singleton to interface with board path objects
class boardPathCachingIO {
	private static $instance = null;
	private $tablename, $databaseConnection;
	
	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			self::$instance = new self($dbSettings);
		}
		return self::$instance;
	}
	
	public static function getInstance() {
		return self::$instance;
	}

	public function __wakeup() { throw new Exception("Unserialization is not allowed.");}
	private function __clone() {}
	private function __construct($dbSettings) {
		$this->tablename = $dbSettings['BOARD_PATH_CACHE_TABLE'];

		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
	}
	
	public function getRowByID($id) {
		$query = "SELECT * FROM {$this->tablename} WHERE id = ?";
		$boardCachedRow = $this->databaseConnection->fetchAsClass($query, [$id], 'cachedBoardPath');
		
		return $boardCachedRow ?? false;
	}
	
	 public function deleteRowByID($id) {
		$query = "DELETE FROM {$this->tablename} WHERE id = ?";
		$board = $this->databaseConnection->execute($query, [$id]);
	}
	
	public function getRowByBoardUID($uid) {
		$query = "SELECT * FROM {$this->tablename} WHERE boardUID = ?";
		$boardCachedRow = $this->databaseConnection->fetchAsClass($query, [$uid], 'cachedBoardPath');
		
		return $boardCachedRow ?? false;
	}

	public function  getAllRows() {
		$query = "SELECT * FROM {$this->tablename}";
		$boardCachedPaths = $this->databaseConnection->fetchAllAsClass($query, [], 'cachedBoardPath');
		return $boardCachedPaths;
	}
	
    public function updateBoardPathCacheByBoardUID($board_uid, $board_path) {
        $query = "UPDATE {$this->tablename} SET board_path = :board_path WHERE boardUID = :boardUID";
        $params = [
            ':board_path' => strval($board_path),
            ':boardUID' => intval($board_uid),
        ];

        $this->databaseConnection->execute($query, $params);
    }

	public function addNewCachedBoardPath($board_uid, $board_path) {
		$query = "INSERT INTO {$this->tablename} (boardUID, board_path) VALUES(:boardUID, :board_path)";
		$params = [
			':boardUID' => intval($board_uid),
            ':board_path' => strval($board_path),
		];
		$this->databaseConnection->execute($query, $params);
	}

}
