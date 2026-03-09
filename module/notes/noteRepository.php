<?php

namespace Kokonotsuba\Modules\notes;

use Kokonotsuba\database\databaseConnection;

/**
 * Table structure
 * id INT AUTO_INCREMENT PRIMARY KEY,
 * post_uid INT NOT NULL,
 * note_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
 * added_by INT NULL,
 * note_text TEXT NOT NULL,
 */

class noteRepository {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $noteTable
	) {}

	public function insertNote(
		int $postUid, 
		string $noteText, 
		int $accountId
	): void {
		// build query for note insertion
		$query = "INSERT INTO $this->noteTable (post_uid, note_text, added_by) 
				  VALUES (:post_uid, :note_text, :added_by)";
		
		// note parameters
		$params = [
			':post_uid' => $postUid,
			':note_text' => $noteText,
			':added_by' => $accountId
		];

		// execute the query to insert the note
		$this->databaseConnection->execute($query, $params);
	}
}