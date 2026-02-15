<?php

namespace Kokonotsuba\Modules\antiFlood;

use Kokonotsuba\database\databaseConnection;

class submissionRepository {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $submissionTable
	) {}

	/**
	 * Get the most recent submission timestamp for a board.
	 * Uses atomic SELECT with FOR UPDATE to lock the row and prevent race conditions.
	 * Must be called within a transaction context for FOR UPDATE to be effective.
	 * 
	 * @param int $boardUID The board UID to query
	 * @return string|null The ISO 8601 timestamp of the last submission, or null if no submissions exist
	 */
	public function getLastSubmissionTimeForBoard(int $boardUID): ?string {
		// Use FOR UPDATE to lock the row during concurrent access
		// This prevents other transactions from modifying this row until we're done reading
		// Requires the caller to be within a transaction context
		$query = "
			SELECT last_submission_timestamp
			FROM {$this->submissionTable}
			WHERE board_uid = :board_uid
			FOR UPDATE
		";

		$params = [
			':board_uid' => $boardUID
		];

		$result = $this->databaseConnection->fetchColumn($query, $params);

		return $result ?: null;
	}

	/**
	 * Record a new submission for a board.
	 * Uses UPSERT pattern since board_uid is UNIQUE (one row per board).
	 * Updates the timestamp if the row exists, inserts if it doesn't.
	 * 
	 * @param int $boardUID The board UID
	 * @return bool Success status
	 */
	public function recordSubmission(int $boardUID): bool {
		// Since board_uid is UNIQUE, we use INSERT...ON DUPLICATE KEY UPDATE
		// to atomically update or insert the timestamp
		$query = "
			INSERT INTO {$this->submissionTable} 
			(board_uid, last_submission_timestamp)
			VALUES (:board_uid, CURRENT_TIMESTAMP(3))
			ON DUPLICATE KEY UPDATE
			last_submission_timestamp = CURRENT_TIMESTAMP(3)
		";

		$params = [
			':board_uid' => $boardUID
		];

		return $this->databaseConnection->execute($query, $params);
	}
}
