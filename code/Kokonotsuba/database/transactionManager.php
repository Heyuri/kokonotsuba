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

namespace Kokonotsuba\database;

use PDOException;
use Throwable;

class transactionManager {
	private databaseConnection $databaseConnection;

	public function __construct(databaseConnection $databaseConnection) {
		$this->databaseConnection = $databaseConnection;
	}

	/**
	 * Whether a transaction is already open on the connection.
	 *
	 * Callers that want to batch their own writes have to ask: begin() and commit() here act on
	 * whatever transaction is current, so committing while nested inside somebody else's would end
	 * theirs, not yours.
	 */
	public function inTransaction(): bool {
		return $this->databaseConnection->inTransaction();
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
	 *
	 * Nests safely: if a transaction is already open, the callback simply joins it
	 * and the outermost run() keeps ownership of the commit/rollback. Committing
	 * here would end the caller's transaction early.
	 */
	public function run(callable $callback): mixed {
		if ($this->databaseConnection->inTransaction()) {
			return $callback();
		}

		$this->databaseConnection->beginTransaction();
		try {
			$result = $callback();
			$this->commit();
			return $result;
		} catch (Throwable $e) {
			$this->rollback();
			throw $e;
		}
	}

	/**
	 * run(), started again when the server picks this transaction as a deadlock victim.
	 *
	 * Only for callbacks that are safe to run twice: the victim is rolled back whole and the
	 * callback runs again from the top, so anything it did outside the database is done again. Joined to an open transaction it is a plain run(),
	 * because it is the outer one that was rolled back and only its owner can start it again.
	 */
	public function runRetryingDeadlocks(callable $callback, int $attempts = 3): mixed {
		if ($this->databaseConnection->inTransaction()) {
			return $callback();
		}

		for ($attempt = 1; ; $attempt++) {
			try {
				return $this->run($callback);
			} catch (PDOException $e) {
				// 1213: ER_LOCK_DEADLOCK
				if ($attempt >= $attempts || (int)($e->errorInfo[1] ?? 0) !== 1213) {
					throw $e;
				}
				// let the transaction that won finish
				usleep(random_int(5000, 40000) * $attempt);
			}
		}
	}
}
