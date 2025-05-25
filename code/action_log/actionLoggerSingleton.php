<?php
// database actionlog singleton

class actionLogger {
	private static $instance = null;
	private $databaseConnection, $tableName, $boardTableName;



	public function __wakeup() { throw new Exception("Unserialization is not allowed."); }
	private function __clone() {}
	private function __construct($dbSettings) { 
		$this->tableName = $dbSettings['ACTIONLOG_TABLE'];
		$this->boardTableName = $dbSettings['BOARD_TABLE'];
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
	
	
	public function getAmountOfLogEntries($filters = []): int {
		$query = "SELECT COUNT(*) FROM {$this->tableName} WHERE 1";
		$params = [];
		bindActionLogFiltersParameters($params, $query, $filters);
		$count = $this->databaseConnection->fetchColumn($query, $params);
		return $count ?? 0;
	}
	
	public function getFullActionLog(): array {
		$query = "SELECT * FROM {$this->tableName} ORDER BY time_added";
		$logs = $this->databaseConnection->fetchAllAsClass($query, [], 'loggedActionEntry');
		
		return $logs ?? [];
	}
	
	public function getSpecifiedLogEntries(int $amount = 0, int $offset = 0, array $filters = [], string $order = 'time_added'): array {
		// Whitelist of allowed columns for ordering
		$allowedOrderFields = ['time_added', 'user_id', 'action_type'];
		if (!in_array($order, $allowedOrderFields, true)) {
			$order = 'time_added'; // fallback to default
		}
		
		// Sanitize limit and offset
		if($amount == 0) return []; // Dont bother querying for nothing
		$offset = max($offset, 0); // Ensure offset is not negative
		
		$query = "SELECT * FROM {$this->tableName} WHERE 1";
		$params = [];
		
		// Apply filters
		bindActionLogFiltersParameters($params, $query, $filters);
		
		// Add ORDER BY clause
		$query .= " ORDER BY {$order} DESC";
		
		// Add LIMIT and OFFSET directly (not bound, to avoid DB compatibility issues)
		$query .= " LIMIT {$amount} OFFSET {$offset}";
		
		// Fetch results as a class
		$logs = $this->databaseConnection->fetchAllAsClass($query, $params, 'loggedActionEntry');
		
		return $logs ?? [];
	}
	
	
	public function logAction(string $actionString, int $board_uid): void {
		$AccountIO = AccountIO::getInstance();
		$staffSession = new staffAccountFromSession;
		$IPAddress = new IPAddress;

		$name = $staffSession->getUsername();
		$roleEnum = $staffSession->getRoleLevel();
		$role = $roleEnum->value;
		
		if($roleEnum->isStaff()) {
			$AccountIO->incrementAccountActionRecordByID($staffSession->getUID());
		}

		$query = "INSERT INTO {$this->tableName} (name, role, log_action, ip_address, board_uid, board_title) VALUES(:name, :role, :log_action, :ip_address, :board_uid, (SELECT board_title FROM {$this->boardTableName} WHERE board_uid = :board_uid LIMIT 1))";
		$params = [
			':name' => $name,
			':role' => $role,
			':log_action' => $actionString,
			':ip_address' => $IPAddress,
			':board_uid' => $board_uid,
		];
		
		$this->databaseConnection->execute($query, $params);
	}

}
