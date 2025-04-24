<?php
/**
 * Singleton to interface with boards for Kokonotsuba!
 */
class boardIO {
	private static $instance = null;
	private $tablename, $databaseConnection;

	// Store results to avoid over-querying within a request
	private static array $boardResultCache = [];

	// Create the singleton instance
	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			self::$instance = new self($dbSettings);
		}
		return self::$instance;
	}

	// Get the singleton instance
	public static function getInstance() {
		return self::$instance;
	}

	// Prevent unserialization
	public function __wakeup() {
		throw new Exception("Unserialization is not allowed.");
	}

	// Prevent cloning
	private function __clone() {}

	// Constructor
	private function __construct($dbSettings) {
		$this->tablename = $dbSettings['BOARD_TABLE'];
		$this->databaseConnection = DatabaseConnection::getInstance();
	}

	// Internal cache handler
	private function cacheMethodResult(string $key, callable $fn) {
		if (isset(self::$boardResultCache[$key])) {
			return self::$boardResultCache[$key];
		}
		return self::$boardResultCache[$key] = $fn();
	}

	// Invalidate per-request board result cache
	private function invalidateBoardCache(): void {
		self::$boardResultCache = [];
	}

	// Get a board object by UID
	public function getBoardByUID($uid) {
		$cacheKey = __METHOD__ . ':' . intval($uid);

		return $this->cacheMethodResult($cacheKey, function () use ($uid) {
			$query = "SELECT * FROM {$this->tablename} WHERE board_uid = ?";
			return $this->databaseConnection->fetchAsClass($query, [$uid], 'board');
		});
	}


	// Delete board by UID
	public function deleteBoardByUID($uid) {
		$query = "DELETE FROM {$this->tablename} WHERE board_uid = ?";
		$this->databaseConnection->execute($query, [$uid]);
		$this->invalidateBoardCache(); // clear cache
	}

	// Get all boards (cached)
	public function getAllBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->tablename}";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'board');
		});
	}

	// Get all boards with UID > 0 (cached)
	public function getAllRegularBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->tablename} WHERE board_uid > 0";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'board');
		});
	}

	// Get only UIDs of all regular boards (cached)
	public function getAllRegularBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->tablename} WHERE board_uid > 0";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'board');
		});
	}

	// Get only UIDs of all boards (cached)
	public function getAllBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->tablename}";
			$boards = $this->databaseConnection->fetchAllAsIndexArray($query, []);
			return array_merge(...$boards);
		});
	}

	// Get all listed boards (cached)
	public function getAllListedBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->tablename} WHERE listed = true";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'board');
		});
	}

	// Get only UIDs of listed boards (cached)
	public function getAllListedBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->tablename} WHERE listed = true";
			$boards = $this->databaseConnection->fetchAllAsIndexArray($query, []);
			return array_merge(...$boards);
		});
	}

	// Get board objects by UID array (cached per UID combination)
	public function getBoardsFromUIDs($uidList) {
		if (!is_array($uidList)) $uidList = [$uidList];
		$cacheKey = __METHOD__ . ':' . implode(',', $uidList);

		return $this->cacheMethodResult($cacheKey, function () use ($uidList) {
			$placeholders = implode(', ', array_fill(0, count($uidList), '?'));
			$query = "SELECT * FROM {$this->tablename} WHERE board_uid IN ({$placeholders})";
			return $this->databaseConnection->fetchAllAsClass($query, $uidList, 'board');
		});
	}

	// Add a new board to the database
	public function addNewBoard($board_identifier, $board_title, $board_sub_title, $listed, $config_name, $storage_directory_name) {
		$query = "INSERT INTO {$this->tablename} (board_identifier, board_title, board_sub_title, listed, config_name, storage_directory_name)
		          VALUES(:board_identifier, :board_title, :board_sub_title, :listed, :config_name, :storage_directory_name)";
		$params = [
			':board_identifier' => $board_identifier,
			':board_title' => $board_title,
			':board_sub_title' => $board_sub_title,
			':listed' => $listed,
			':config_name' => $config_name,
			':storage_directory_name' => $storage_directory_name
		];
		$this->databaseConnection->execute($query, $params);
		$this->invalidateBoardCache(); // clear cache
	}

	// Edit board values
	public function editBoardValues(board $boardToBeEdited, $fields) {
		if (!$fields) throw new Exception("Fields left empty.");

		$params = [];
		$assignments = [];

		if (!empty($fields['board_identifier'])) {
			$assignments[] = "board_identifier = :board_identifier";
			$params[':board_identifier'] = $fields['board_identifier'];
		}
		if (!empty($fields['board_title'])) {
			$assignments[] = "board_title = :board_title";
			$params[':board_title'] = $fields['board_title'];
		}
		if (!empty($fields['board_sub_title'])) {
			$assignments[] = "board_sub_title = :board_sub_title";
			$params[':board_sub_title'] = $fields['board_sub_title'];
		}
		if (!empty($fields['config_name'])) {
			$assignments[] = "config_name = :config_name";
			$params[':config_name'] = $fields['config_name'];
		}
		if (!empty($fields['storage_directory_name'])) {
			$assignments[] = "storage_directory_name = :storage_directory_name";
			$params[':storage_directory_name'] = $fields['storage_directory_name'];
		}
		if (isset($fields['listed'])) {
			$assignments[] = "listed = :listed";
			$params[':listed'] = $fields['listed'] ? 1 : 0;
		}

		if (empty($assignments)) throw new Exception("No valid fields provided to update.");

		$query = "UPDATE {$this->tablename} SET " . implode(", ", $assignments) . " WHERE board_uid = :board_uid";
		$params[':board_uid'] = $boardToBeEdited->getBoardUID();

		$this->databaseConnection->execute($query, $params);
		$this->invalidateBoardCache(); // clear cache
	}

	// Get the next auto-increment board UID (cached)
	public function getNextBoardUID() {
		return $this->cacheMethodResult(__METHOD__, function () {
			return $this->databaseConnection->getNextAutoIncrement($this->tablename);
		});
	}

	// Get the last board UID in the table (cached)
	public function getLastBoardUID() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT MAX(board_uid) FROM {$this->tablename}";
			return $this->databaseConnection->fetchColumn($query);
		});
	}
}
