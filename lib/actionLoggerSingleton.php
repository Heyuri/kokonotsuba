<?php
// database actionlog singleton

class actionLogger {
	private static $instance = null;
	private $databaseConnection, $tableName, $boardTableName;



	public function __wakeup() { throw new Exception("Unserialization is not allowed."); }
	private function __clone() {}
	private function __construct($dbSettings) { 
		$this->tableName = $dbSettings['ACTIONLOG_TABLE'];
		$this->boardTable = $dbSettings['BOARD_TABLE'];
		$this->databaseConnection = DatabaseConnection::getInstance(); // Get the PDO instance
		
	}
	
	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			self::$instance = new self($dbSettings);
		}
		return self::$instance;
	}
	
	public static function getInstance() {
		return self::$instance;
	}
	
	//set the parameters and handle filters for the filter query
	private function bindfiltersParameters(&$params, &$query, $filters) {
		if(isset($filters['id'])) {
			$query .= " AND id = :id";
			$params[':id'] = intval($filters['id']);
		}
		if(isset($filters['time_added'])) {
			$query .= " AND time_added = :time_added";
			$params[':time_added'] = $filters['time_added'];
		}
		if(!empty($filters['name'])) {
			$query .= " AND name = :name";
			$params[':name'] = strval($filters['name']);
		}
		if (isset($filters['role']) && !empty($filters['role'])) {
			$query .= " AND (";
			foreach ($filters['role'] as $index => $role) {
				$query .= ($index > 0 ? " OR " : "") . "role = :role_$index";
				$params[":role_$index"] = intval($role);
			}
			$query .= ")";
		}
		
		if (isset($filters['board']) && !empty($filters['board'])) {
			$query .= " AND (";
			foreach ($filters['board'] as $index => $board) {
				$query .= ($index > 0 ? " OR " : "") . "board_uid = :board_$index";
				$params[":board_$index"] = $board;
			}
			$query .= ")";
		}

		if(isset($filters['log_action'])) {
			$query .= " AND log_action LIKE :log_action";
			$params[':log_action'] = strval('%'.$filters['log_action'].'%');
		}
		
		//generate part of the filter query for timestamp filtration
		if(!empty($filters['date_after']) && empty($filters['date_before'])) {
			$query .= " AND date_added >= :date_after";
			$params[':date_after'] = $filters['date_after'];
		}
		if(!empty($filters['date_before']) && empty($filters['date_after'])) {
			$query .= " AND date_added <= :date_before";
			$params[':date_before'] = $filters['date_before'];
		}
		if(!empty($filters['date_before']) && !empty($filters['date_after'])) {
			$query .= " AND date_added BETWEEN :date_before AND :date_after";


			$lowerBoundDate = min($filters['date_before'], $filters['date_after']);
			$upperBoundDate = max($filters['date_before'], $filters['date_after']);
			$params[':date_before'] = strval($lowerBoundDate);
			$params[':date_after'] = strval($upperBoundDate);
		}
		if(isset($filters['ip_address'])) {
			//adjust for wildcard
			$ip_pattern = preg_quote($filters['ip_address'], '/');
			$ip_pattern = str_replace('\*', '.*', $ip_pattern);
    		$ip_regex = "^$ip_pattern$";


			$query .= " AND ip_address REGEXP :ip_regex";    
			$params[':ip_regex'] = $ip_regex;
		}
		
		if(isset($filters['deleted'])) {
			$query .= " AND log_action LIKE :delete";
			$params[':delete'] = strval('%'."delete".'%');
		}
		if(isset($filters['ban'])) {
			$query .= " AND (log_action LIKE :ban OR log_action LIKE :mute OR log_action LIKE :warn)";
			$params[':ban'] = strval('%'."ban".'%');
			$params[':mute'] = strval('%'."mute".'%');
			$params[':warn'] = strval('%'."warn".'%');
		}
	}
	
	public function getAmountOfLogEntries($filters = []) {
		$query = "SELECT COUNT(*) FROM {$this->tableName} WHERE 1";
		$params = [];
		$this->bindfiltersParameters($params, $query, $filters);
		$count = $this->databaseConnection->fetchColumn($query, $params);
		return $count;
	}
	
	public function getFullActionLog() {
		$query = "SELECT * FROM {$this->tableName} ORDER BY time_added";
		$logs = $this->databaseConnection->fetchAllAsClass($query, [], 'loggedActionEntry');
		
		return $logs;
	}
	
	public function getSpecifiedLogEntries($amount, $offset = 0, $filters = [], $order = 'time_added') {
		$query = "SELECT * FROM {$this->tableName} WHERE 1";
		$params = [];
		
		$this->bindfiltersParameters($params, $query, $filters); //apply filtration to query
		
		$query .= " ORDER BY $order  DESC LIMIT $amount OFFSET $offset";
		$logs = $this->databaseConnection->fetchAllAsClass($query, $params, 'loggedActionEntry');
	
		return $logs;
	}
	
	public function logAction($actionString, $board_uid){
		$AccountIO = AccountIO::getInstance();
		$staffSession = new staffAccountFromSession;

		$name = $staffSession->getUsername();
		$role = $staffSession->getRoleLevel();
		
		if($role) $AccountIO->incrementAccountActionRecordByID($staffSession->getUID());

		$query = "INSERT INTO {$this->tableName} (name, role, log_action, ip_address, board_uid, board_title) VALUES(:name, :role, :log_action, :ip_address, :board_uid, (SELECT board_title FROM {$this->boardTable} WHERE board_uid = :board_uid LIMIT 1))";
		$params = [
			':name' => $name,
			':role' => $role,
			':log_action' => $actionString,
			':ip_address' => getREMOTE_ADDR(),
			':board_uid' => $board_uid,
		];
		
		$this->databaseConnection->execute($query, $params);
	}

}
