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

	public function noteOwnedByAccount(int $accountId, int $noteId): bool {
		// build query to check if the note with the given ID is owned by the account
		$query = "SELECT 1 FROM $this->noteTable WHERE id = :note_id AND added_by = :account_id";
		
		// parameters for the query
		$params = [
			':note_id' => $noteId,
			':account_id' => $accountId
		];

		// return true if note is found, false otherwise
		return $this->databaseConnection->fetchValue($query, $params) === 1;
	}

	public function deleteNote(int $noteId): void {
		// build query to delete the note with the given ID
		$query = "DELETE FROM $this->noteTable WHERE id = :note_id";
		
		// parameters for the query
		$params = [
			':note_id' => $noteId
		];

		// execute the query to delete the note
		$this->databaseConnection->execute($query, $params);
	}

	public function editNote(int $noteId, string $newText): void {
		// build query to update the note text for the given note ID
		$query = "UPDATE $this->noteTable SET note_text = :note_text WHERE id = :note_id";

		// parameters for the query
		$params = [
			':note_id' => $noteId,
			':note_text' => $newText
		];

		// execute the query to update the note
		$this->databaseConnection->execute($query, $params);
	}
}