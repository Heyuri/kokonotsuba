<?php

namespace Kokonotsuba\database;

use Exception;
use InvalidArgumentException;
use Kokonotsuba\error\BoardException;
use PDO;
use PDOException;
use RuntimeException;

class databaseConnection {
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
			self::$instance = new databaseConnection($dbSettings);
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
			throw new RuntimeException('There was a problem connecting to the database.', 0, $e);
		}
	}

	// Public method to execute a query (for INSERT, UPDATE, DELETE)
	public function execute(string $query, array $params = []) {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		return $stmt->execute();
	}

	// Bind parameters with proper PDO types (int params as PARAM_INT so LIMIT/OFFSET work)
	private function bindTypedParams(\PDOStatement $stmt, array $params): void {
		foreach ($params as $key => $value) {
			// Positional params (0-indexed array) need 1-indexed keys for bindValue
			$bindKey = is_int($key) ? $key + 1 : $key;

			if (is_int($value)) {
				$stmt->bindValue($bindKey, $value, PDO::PARAM_INT);
			} elseif (is_bool($value)) {
				$stmt->bindValue($bindKey, $value, PDO::PARAM_BOOL);
			} elseif (is_null($value)) {
				$stmt->bindValue($bindKey, $value, PDO::PARAM_NULL);
			} else {
				$stmt->bindValue($bindKey, $value, PDO::PARAM_STR);
			}
		}
	}

	// Transaction methods
	public function inTransaction(): bool {
		return $this->pdo->inTransaction();
	}

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
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_CLASS, $className);
	}

	public function fetchAllAsArray(string $query, array $params = []) {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function fetchAllAsIndexArray(string $query, array $params = []) {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_NUM);
	}
	
	public function fetchAsClass(string $query, array $params = [], string $className = '') {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
		$stmt->setFetchMode(PDO::FETCH_CLASS, $className);
		return $stmt->fetch();
	}

	public function fetchColumn(string $query, array $params = [], int $columnIndex = 0) {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
		return $stmt->fetchColumn($columnIndex);
	}

	public function fetchOne(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC) {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
		return $stmt->fetch($fetchMode);
	}

	public function fetchValue(string $query, array $params = [], int $columnIndex = 0) {
		$stmt = $this->pdo->prepare($query);
		$this->bindTypedParams($stmt, $params);
		$stmt->execute();
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

	/**
	 * Fetches the default MySQL InnoDB FULLTEXT stopwords.
	 *
	 * The result is cached for the lifetime of the request to avoid
	 * repeated INFORMATION_SCHEMA queries.
	 *
	 * @return string[] List of stopwords
	 */
	public function fetchFulltextStopWords(): array {
		static $cache = null;

		if ($cache !== null) {
			return $cache;
		}

		// Only supported for MySQL / InnoDB
		$query = 'SELECT value FROM INFORMATION_SCHEMA.INNODB_FT_DEFAULT_STOPWORD';

		$rows = $this->fetchAllAsIndexArray($query);

		$cache = array_map(
			fn($row) => mb_strtolower($row[0]),
			$rows
		);

		return $cache;
	}

}
