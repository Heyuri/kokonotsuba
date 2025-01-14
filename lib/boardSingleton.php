<?php
//singleton to interface with board objects
class boardIO {
	private static $instance = null;
	private $tablename, $postNumberTable, $databaseConnection;
	
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
		$this->tablename = $dbSettings['BOARD_TABLE'];
		$this->postNumberTable = $dbSettings['POST_NUMBER_TABLE'];
		
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
	}
	
	public function getBoardByUID($uid) {
		$query = "SELECT * FROM {$this->tablename} WHERE board_uid = ?";
		$board = $this->databaseConnection->fetchAsClass($query, [$uid], 'board');
		
		if(!$board)  die("Board ($uid) not found in `{$this->tablename}`. Contact the Administrator if you believe this is a mistake."); //board was not found
		return $board;
	}
	
	public function deleteBoardByUID($uid) {
		$query = "DELETE FROM {$this->tablename} WHERE board_uid = ?";
		$board = $this->databaseConnection->execute($query, [$uid]);
	}
	
	public function  getAllBoards() {
		$query = "SELECT * FROM {$this->tablename}";
		$boards = $this->databaseConnection->fetchAllAsClass($query, [], 'board');
		return $boards;
	}
	
	public function getAllBoardUIDs() {
		$query = "SELECT board_uid FROM {$this->tablename}";
		$boards = $this->databaseConnection->fetchAllAsIndexArray($query, []);
		return array_merge(...$boards);
	}
	
	public function getAllListedBoards() {
		$query = "SELECT * FROM {$this->tablename} WHERE listed = true";
		$boards = $this->databaseConnection->fetchAllAsClass($query, [], 'board');
		return $boards;
	}

	public function getAllListedBoardUIDs() {
		$query = "SELECT board_uid FROM {$this->tablename} WHERE listed = true";
		$boards = $this->databaseConnection->fetchAllAsIndexArray($query, []);
		return array_merge(...$boards);
	}

	public function getBoardsFromUIDs($uidList)  {
		if(!is_array($uidList)) $uidList = [$uidList];

		$uidList = implode(', ', $uidList);
		$query = "SELECT * FROM {$this->tablename} WHERE board_uid IN ({$uidList})";
		
		$boards = $this->databaseConnection->fetchAllAsClass($query, [], 'board');

		return $boards;
	}
	
	public function addNewBoard($board_identifier, $board_title, $board_sub_title, $listed, $config_name, $storage_directory_name) {
		$query = "INSERT INTO {$this->tablename} (board_identifier, board_title, board_sub_title, listed, config_name, storage_directory_name) VALUES(:board_identifier, :board_title, :board_sub_title, :listed, :config_name, :storage_directory_name)";
		$params = [
			':board_identifier' => $board_identifier,
			':board_title' => $board_title,
			':board_sub_title' => $board_sub_title,
			':listed' => $listed,
			':config_name' => $config_name,
			':storage_directory_name' => $storage_directory_name
		];
		$this->databaseConnection->execute($query, $params);
	}
	
	public function editBoardValues(board $boardToBeEdited, $fields) {
		if (!$fields) {
			throw new Exception("Fields left empty.");
		}
		
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
	
		if (empty($assignments)) {
			throw new Exception("No valid fields provided to update.");
		}
		
		// Join assignments with commas
		$query = "UPDATE {$this->tablename} SET " . implode(", ", $assignments);
		$query .= " WHERE board_uid = :board_uid";
		$params[':board_uid'] = $boardToBeEdited->getBoardUID();
	
		$this->databaseConnection->execute($query, $params);
	}

	public function getNextBoardUID() {
		return $this->databaseConnection->getNextAutoIncrement($this->tablename);
	}

	public function getLastBoardUID() {
		$query = "SELECT MAX(board_uid) FROM {$this->tablename}";
		$board_uid = $this->databaseConnection->fetchColumn($query);
		return $board_uid;
	}	

}
