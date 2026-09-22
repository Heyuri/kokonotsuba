<?php

namespace Kokonotsuba\database;

use Exception;
use InvalidArgumentException;
use Kokonotsuba\error\BoardException;
use Kokonotsuba\debug\requestMetrics;
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
		// A port is only added when one is configured: on 'localhost' MySQL uses a unix socket,
		// and naming a port there forces TCP instead, which needs a different grant.
		$port = isset($dbSettings['DATABASE_PORT']) && (int)$dbSettings['DATABASE_PORT'] > 0
			? (int)$dbSettings['DATABASE_PORT']
			: null;

		switch ($dbSettings['DATABASE_DRIVER']) {
			case 'mysql':
				$dsn = "mysql:host={$dbSettings['DATABASE_HOST']};"
					.($port !== null && $dbSettings['DATABASE_HOST'] !== 'localhost' ? "port={$port};" : '')
					."dbname={$dbSettings['DATABASE_NAME']};charset={$dbSettings['DATABASE_CHARSET']}";
				break;
			case 'pgsql':
				$dsn = "pgsql:host={$dbSettings['DATABASE_HOST']};"
					.($port !== null ? "port={$port};" : '')
					."dbname={$dbSettings['DATABASE_NAME']};";
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
		try {
			$pdo = new PDO($dsn, $dbSettings['DATABASE_USERNAME'], $dbSettings['DATABASE_PASSWORD'], [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]);
		} catch (PDOException $e) {
			throw new RuntimeException('There was a problem connecting to the database.', 0, $e);
		}

		if ($dbSettings['DATABASE_DRIVER'] === 'mysql') {
			self::disableSnapshotIsolation($pdo);
		}

		return $pdo;
	}

	/**
	 * MariaDB 11.6+ fails a locking read or an update of a row that changed since the
	 * transaction's snapshot (error 1020), where older servers and MySQL wait and read the latest.
	 * Transactions here read before they lock, so two posters at once would lose one of them.
	 * Servers without the variable already behave the old way.
	 */
	private static function disableSnapshotIsolation(PDO $pdo): void {
		try {
			$pdo->exec('SET SESSION innodb_snapshot_isolation = OFF');
		} catch (PDOException) {
			// unknown variable: nothing to switch off
		}
	}

	// Public method to execute a query (for INSERT, UPDATE, DELETE)
	public function execute(string $query, array $params = []) {
		$this->run($query, $params);
		return true;
	}

	/**
	 * Prepare, bind and execute one statement, timed for the debug bar.
	 *
	 * Every query goes through here, so the request's statement count and time are complete.
	 * PDO throws on failure (ERRMODE_EXCEPTION), which is why execute() can answer true.
	 */
	private function run(string $query, array $params): \PDOStatement {
		$started = hrtime(true);
		try {
			$stmt = $this->pdo->prepare($query);
			$this->bindTypedParams($stmt, $params);
			$stmt->execute();
		} finally {
			requestMetrics::recordQuery((hrtime(true) - $started) / 1e9, $query);
		}

		return $stmt;
	}

	/**
	 * Execute a write and report how many rows it touched.
	 *
	 * execute() only reports success, which is no use to a caller that has to say how many rows
	 * a multi-row INSERT wrote or a conditional UPDATE actually changed.
	 *
	 * @param string $query  Raw SQL string with named or positional placeholders.
	 * @param array  $params Bound parameters.
	 * @return int Rows affected by the statement.
	 */
	public function executeWithRowCount(string $query, array $params = []): int {
		return $this->run($query, $params)->rowCount();
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
		return $this->run($query, $params)->fetchAll(PDO::FETCH_CLASS, $className);
	}

	public function fetchAllAsArray(string $query, array $params = []) {
		return $this->run($query, $params)->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public function fetchAllAsIndexArray(string $query, array $params = []) {
		return $this->run($query, $params)->fetchAll(PDO::FETCH_NUM);
	}
	
	public function fetchAsClass(string $query, array $params = [], string $className = '') {
		$stmt = $this->run($query, $params);
		$stmt->setFetchMode(PDO::FETCH_CLASS, $className);
		return $stmt->fetch();
	}

	public function fetchColumn(string $query, array $params = [], int $columnIndex = 0) {
		return $this->run($query, $params)->fetchColumn($columnIndex);
	}

	public function fetchOne(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC) {
		return $this->run($query, $params)->fetch($fetchMode);
	}

	public function fetchValue(string $query, array $params = [], int $columnIndex = 0) {
		return $this->run($query, $params)->fetchColumn($columnIndex);
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
	
			$stmt = $this->run($query, [
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
