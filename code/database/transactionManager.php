<?php
/**
 * TransactionManager.php
 *
 * Manages database transactions using PDO.
 * Provides methods to begin, commit, and roll back transactions,
 * and a high-level `run()` wrapper for safe transactional execution.
 *
 * Usage Example:
 * $transactionManager = new transactionManager($pdo);
 * $transactionManager->run(function() {
 *     // your transactional logic
 * });
 *
 */


class transactionManager {
	private databaseConnection $databaseConnection;

	public function __construct(databaseConnection $databaseConnection) {
		$this->databaseConnection = $databaseConnection;
	}

	public function begin(): void {
		if (!$this->databaseConnection->inTransaction()) {
			$this->databaseConnection->beginTransaction();
		}
	}

	public function commit(): void {
		if ($this->databaseConnection->inTransaction()) {
			$this->databaseConnection->commit();
		}
	}

	public function rollback(): void {
		if ($this->databaseConnection->inTransaction()) {
			$this->databaseConnection->rollBack();
		}
	}

	/**
	 * Execute a callback in a safe transaction.
	 * Rolls back automatically if an exception is thrown.
	 */
	public function run(callable $callback): mixed {
		$this->begin();
		try {
			$result = $callback();
			$this->commit();
			return $result;
		} catch (Throwable $e) {
			$this->rollback();
			throw $e;
		}
	}
}
