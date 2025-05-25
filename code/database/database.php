<?php

class DatabaseConnection {
	private static $instance = null;
	private $pdo;
	private $dbName;

	// Make constructor private to prevent direct instantiation
	private function __construct(array $dbSettings) {
		$this->pdo = $this->createPDOConnection($dbSettings);
		$this->dbName = $dbSettings['DATABASE_NAME'];
	}

	public static function createInstance(array $dbSettings) {
		if (self::$instance === null) {
			self::$instance = new DatabaseConnection($dbSettings);
		}
	}

	public static function getInstance() {
		return self::$instance;
	}

	// Prevent cloning of the instance
	private function __clone() {}
	public function __wakeup() {
		throw new Exception("Cannot unserialize singleton");
	}

	// Create the PDO connection
	private function createPDOConnection(array $dbSettings) {
		switch ($dbSettings['DATABASE_DRIVER']) {
			case 'mysql':
				$dsn = "mysql:host={$dbSettings['DATABASE_HOST']};dbname={$dbSettings['DATABASE_NAME']};charset={$dbSettings['DATABASE_CHARSET']}";
				break;
			case 'pgsql':
				$dsn = "pgsql:host={$dbSettings['DATABASE_HOST']};dbname={$dbSettings['DATABASE_NAME']};";
				break;
			case 'sqlite':
				$dsn = "sqlite:{$dbSettings['DATABASE_NAME']}";
				break;
			case 'sqlsrv':
				$dsn = "sqlsrv:Server={$dbSettings['DATABASE_HOST']};Database={$dbSettings['DATABASE_NAME']}";
				break;
			default:
				throw new InvalidArgumentException("Unsupported driver: {$dbSettings['DATABASE_DRIVER']}");
		}
		// Return the PDO instance
		try {
			return new PDO($dsn, $dbSettings['DATABASE_USERNAME'], $dbSettings['DATABASE_PASSWORD'], [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]);
		} catch (PDOException $e) {
			die("Connection failed: " . $e->getMessage());
		}
	}

	// Public method to execute a query (for INSERT, UPDATE, DELETE)
	public function execute(string $query, array $params = []) {
		$stmt = $this->pdo->prepare($query);
		return $stmt->execute($params);
	}

	// Transaction methods
	public function beginTransaction() {
		$this->pdo->beginTransaction();
	}

	public function commit() {
		$this->pdo->commit();
	}

	public function rollBack() {
		$this->pdo->rollBack();
	}

	public function fetchAllAsClass(string $query, array $params = [], string $className = '') {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_CLASS, $className);
	}

	public function fetchAllAsArray(string $query, array $params = []) {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function fetchAllAsIndexArray(string $query, array $params = []) {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchAll(PDO::FETCH_NUM);
	}
	
	public function fetchAsClass(string $query, array $params = [], string $className = '') {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		$stmt->setFetchMode(PDO::FETCH_CLASS, $className);
		return $stmt->fetch();
	}

	public function fetchColumn(string $query, array $params = [], int $columnIndex = 0) {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchColumn($columnIndex);
	}

	public function fetchOne(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC) {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetch($fetchMode);
	}

	public function fetchValue(string $query, array $params = [], int $columnIndex = 0) {
		$stmt = $this->pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchColumn($columnIndex);
	}

	public function lastInsertId() {
		return $this->pdo->lastInsertId();
	}

	public function getConnection() {
		return $this->pdo;
	}

	public function getNextAutoIncrement(string $tableName) {
		try {
			// Prepare the query to fetch AUTO_INCREMENT value from information_schema
			$query = "SELECT AUTO_INCREMENT 
					  FROM information_schema.TABLES 
					  WHERE TABLE_SCHEMA = :databaseName 
					  AND TABLE_NAME = :tableName";
	
			$stmt = $this->pdo->prepare($query);
			$stmt->execute([
				':databaseName' => $this->dbName,
				':tableName' => $tableName,
			]);
	
			// Fetch the result
			$result = $stmt->fetch(PDO::FETCH_ASSOC);
	
			if ($result && isset($result['AUTO_INCREMENT'])) {
				return (int)$result['AUTO_INCREMENT'];
			}
	
			// Return null if AUTO_INCREMENT value is not found
			return null;
		} catch (PDOException $e) {
			// Handle exceptions by logging or re-throwing
			error_log("Error fetching AUTO_INCREMENT value: " . $e->getMessage());
			return null;
		}
	}
}
