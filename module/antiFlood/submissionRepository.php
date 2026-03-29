<?php

namespace Kokonotsuba\Modules\antiFlood;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for anti-flood post submission timestamp records. */
class submissionRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $submissionTable
	) {
		parent::__construct($databaseConnection, $submissionTable);
	}

	/**
	 * Get the most recent submission timestamp for a board.
	 * Uses atomic SELECT with FOR UPDATE to lock the row and prevent race conditions.
	 * Must be called within a transaction context for FOR UPDATE to be effective.
	 * 
	 * @param int $boardUID The board UID to query
	 * @return string|null The ISO 8601 timestamp of the last submission, or null if no submissions exist
	 */
	public function getLastSubmissionTimeForBoard(int $boardUID): ?string {
		$query = "
			SELECT last_submission_timestamp
			FROM {$this->table}
			WHERE board_uid = :board_uid
			FOR UPDATE
		";

		$result = $this->queryColumn($query, [':board_uid' => $boardUID]);

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
		$query = "
			INSERT INTO {$this->table} 
			(board_uid, last_submission_timestamp)
			VALUES (:board_uid, CURRENT_TIMESTAMP(3))
			ON DUPLICATE KEY UPDATE
			last_submission_timestamp = CURRENT_TIMESTAMP(3)
		";

		return $this->query($query, [':board_uid' => $boardUID]);
	}
}
