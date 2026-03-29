<?php

namespace Kokonotsuba\Modules\antiSpam;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for spam string filter rules. */
class antiSpamRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $spamStringRulesTable,
		private string $accountTable
	) {
		parent::__construct($databaseConnection, $spamStringRulesTable);
	}

	/**
	 * Fetch all active spam rules applicable to the provided post fields.
	 *
	 * @param string|null $subject Post subject to test, or null.
	 * @param string|null $comment Post comment to test, or null.
	 * @param string|null $name    Poster name to test, or null.
	 * @param string|null $email   Poster email to test, or null.
	 * @return array|false Array of matching rule rows, or false if none.
	 */
	public function getActiveSpamStringRules(
		?string $subject,
		?string $comment,
		?string $name,
		?string $email
	): false|array {
		$query = "
			SELECT *
				FROM {$this->table}
			WHERE is_active = 1
			  AND (
				(apply_subject = 1 AND :subject IS NOT NULL) OR
				(apply_comment = 1 AND :comment IS NOT NULL) OR
				(apply_name = 1 AND :name IS NOT NULL) OR
				(apply_email = 1 AND :email IS NOT NULL)
			  )
		";

		$params = [
			':subject' => $subject,
			':comment' => $comment,
			':name' => $name,
			':email' => $email,
		];

		return $this->queryAll($query, $params);
	}

	/**
	 * Insert a new spam rule.
	 *
	 * @param string      $pattern        Pattern to match against.
	 * @param string      $matchType      Match strategy: 'contains', 'exact', 'regex', etc.
	 * @param int         $applySubject   Whether to test the subject field (1/0).
	 * @param int         $applyComment   Whether to test the comment field (1/0).
	 * @param int         $applyName      Whether to test the name field (1/0).
	 * @param int         $applyEmail     Whether to test the email field (1/0).
	 * @param int         $caseSensitive  Whether the match is case-sensitive (1/0).
	 * @param string|null $userMessage    Custom message shown to the blocked user, or null.
	 * @param string|null $description    Internal description for admins, or null.
	 * @param string      $action         Action to take on match ('reject', etc.).
	 * @param int|null    $maxDistance    Max edit distance for fuzzy matching, or null.
	 * @param int|null    $createdBy      Account ID of the creating staff member, or null.
	 * @return bool True on success.
	 */
	public function insertRow(
		string $pattern,
		string $matchType = 'contains',
		int $applySubject = 1,
		int $applyComment = 1,
		int $applyName = 1,
		int $applyEmail = 1,
		int $caseSensitive = 0,
		?string $userMessage = null,
		?string $description = null,
		string $action = 'reject',
		?int $maxDistance = null,
		?int $createdBy = null
	): bool {
		$this->insert([
			'pattern' => $pattern,
			'match_type' => $matchType,
			'apply_subject' => $applySubject,
			'apply_comment' => $applyComment,
			'apply_name' => $applyName,
			'apply_email' => $applyEmail,
			'case_sensitive' => $caseSensitive,
			'user_message' => $userMessage,
			'description' => $description,
			'action' => $action,
			'max_distance' => $maxDistance,
			'created_by' => $createdBy,
		]);
		return true;
	}

	/**
	 * Fetch a paginated list of spam rule entries, including the creating account's username.
	 *
	 * @param int $limit  Maximum entries to return.
	 * @param int $offset Pagination offset.
	 * @return array|false Array of rows, or false if none.
	 */
	public function getEntries(int $limit, int $offset): false|array {
		$query = "
			SELECT s.*, a.username AS created_by_username
				FROM {$this->table} s
			LEFT JOIN {$this->accountTable} a ON a.id = s.created_by
			ORDER BY s.id DESC
			LIMIT {$limit} OFFSET {$offset}
		";

		return $this->queryAll($query);
	}

	/**
	 * Count the total number of spam rule entries.
	 *
	 * @return int Entry count.
	 */
	public function getTotalEntries(): int {
		return $this->count();
	}

	/**
	 * Delete a set of spam rule entries by their primary keys.
	 *
	 * @param array $entryIDs Array of integer primary keys to delete.
	 * @return void
	 */
	public function deleteEntries(array $entryIDs): void {
		$placeholders = pdoPlaceholdersForIn($entryIDs);
		$this->query("DELETE FROM {$this->table} WHERE id IN $placeholders", $entryIDs);
	}

	/**
	 * Fetch a single spam rule entry by its primary key, including the creating account's username.
	 *
	 * @param int $id Entry primary key.
	 * @return array|false Associative row, or false if not found.
	 */
	public function getEntryById(int $id): false|array {
		$query = "SELECT s.*, a.username AS created_by_username
					FROM {$this->table} s
					LEFT JOIN {$this->accountTable} a ON a.id = s.created_by
					WHERE s.id = :id";

		return $this->queryOne($query, [':id' => $id]);
	}

	/**
	 * Update whitelisted columns on an existing spam rule entry.
	 *
	 * @param int   $entryId Entry primary key.
	 * @param array $fields  Map of column names to new values.
	 * @return bool True on success, false if no fields provided.
	 */
	public function updateRow(int $entryId, array $fields): bool {
		if (empty($fields)) {
			return false;
		}
		$this->updateWhere($fields, 'id', $entryId);
		return true;
	}

}