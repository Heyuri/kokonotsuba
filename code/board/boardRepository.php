<?php

class boardRepository {
	// Store results to avoid over-querying within a request
	private static array $boardResultCache = [];

	// Constructor
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $boardTable
	) {}

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
			$query = "SELECT * FROM {$this->boardTable} WHERE board_uid = ?";
			return $this->databaseConnection->fetchAsClass($query, [$uid], 'boardData');
		});
	}


	// Delete board by UID
	public function deleteBoardByUID($uid) {
		$query = "DELETE FROM {$this->boardTable} WHERE board_uid = ?";
		$this->databaseConnection->execute($query, [$uid]);
		$this->invalidateBoardCache(); // clear cache
	}

	// Get all boards (cached)
	public function getAllBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->boardTable}";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'boardData');
		});
	}

	// Get all boards with UID > 0 (cached)
	public function getAllRegularBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->boardTable} WHERE board_uid > 0";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'boardData');
		});
	}

	// Get only UIDs of all regular boards (cached)
	public function getAllRegularBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->boardTable} WHERE board_uid > 0";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'boardData');
		});
	}

	// Get only UIDs of all boards (cached)
	public function getAllBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->boardTable}";
			$boards = $this->databaseConnection->fetchAllAsIndexArray($query, []);
			return array_merge(...$boards);
		});
	}

	// Get all listed boards (cached)
	public function getAllListedBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->boardTable} WHERE listed = true";
			return $this->databaseConnection->fetchAllAsClass($query, [], 'boardData');
		});
	}

	// Get only UIDs of listed boards (cached)
	public function getAllListedBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->boardTable} WHERE listed = true";
			$boards = $this->databaseConnection->fetchAllAsIndexArray($query, []);
			return array_merge(...$boards);
		});
	}

	// Get board objects by UID array (cached per UID combination)
	public function getBoardsFromUIDs(array $uidList): array {
		// Create a cache key based on the method and UID list
		$cacheKey = __METHOD__ . ':' . $uidList;
	
		return $this->cacheMethodResult($cacheKey, function () use ($uidList) {
			// Generate the in clause
			$inClause = pdoPlaceholdersForIn($uidList);

			// Prepare the query with the sanitized UIDs
			$query = "SELECT * FROM {$this->boardTable} WHERE board_uid IN $inClause";
	
			// re-index the array to avoid parameter errors
			$uidList = array_values($uidList);

			// Fetch the results using the constructed query
			return $this->databaseConnection->fetchAllAsClass($query, $uidList, 'boardData');
		});
	}
	
	

	// Add a new board to the database
	public function addNewBoard($board_identifier, $board_title, $board_sub_title, $listed, $config_name, $storage_directory_name) {
		$query = "INSERT INTO {$this->boardTable} (board_identifier, board_title, board_sub_title, listed, config_name, storage_directory_name)
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
	public function updateBoardByUID(int $boardUID, array $fields): void {
		if (empty($fields)) {
			throw new Exception("No valid fields provided to update.");
		}

		$params = [];
		$assignments = [];

		foreach ($fields as $key => $value) {
			$assignments[] = "{$key} = :{$key}";
			$params[":{$key}"] = $value;
		}

		$params[':board_uid'] = $boardUID;

		$query = "UPDATE {$this->boardTable} SET " . implode(", ", $assignments) . " WHERE board_uid = :board_uid";

		$this->databaseConnection->execute($query, $params);
		$this->invalidateBoardCache(); // clear cache
	}


	// Get the next auto-increment board UID (cached)
	public function getNextBoardUID() {
		return $this->cacheMethodResult(__METHOD__, function () {
			return $this->databaseConnection->getNextAutoIncrement($this->boardTable);
		});
	}

	// Get the last board UID in the table (cached)
	public function getLastBoardUID() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT MAX(board_uid) FROM {$this->boardTable}";
			return $this->databaseConnection->fetchColumn($query);
		});
	}  
}