<?php

namespace Kokonotsuba\Modules\hostNotes;

use Kokonotsuba\ban\ipPatternMatcher;
use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\ip\ipAnonymizer;

/**
 * Repository for staff notes attached to a host rather than a post.
 *
 * Table structure
 * id INT AUTO_INCREMENT PRIMARY KEY,
 * ip_pattern VARCHAR(255) NULL,
 * visitor_token_hash VARCHAR(32) NULL,
 * is_wildcard TINYINT(1) NOT NULL DEFAULT 0,
 * note_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
 * added_by INT NULL,
 * note_text TEXT NOT NULL,
 *
 * A note names exactly one target: a host in ip_pattern, or a browser in visitor_token_hash.
 * Exact patterns are looked up by index; the wildcard rows are pulled whole and matched in PHP,
 * the same split the ban system makes, because a range is not an equality lookup. A token hash
 * is always an equality, so no range ever covers one.
 */
class hostNoteRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $hostNoteTable,
		private readonly string $accountTable
	) {
		parent::__construct($databaseConnection, $hostNoteTable);
		self::validateTableName($this->accountTable);
	}

	/** SELECT list shared by every read, carrying the author's name. */
	private function selectClause(): string {
		return "SELECT n.*, a.username AS note_added_by_username
			FROM {$this->table} n
			LEFT JOIN {$this->accountTable} a ON a.id = n.added_by";
	}

	/**
	 * Insert a note against a host pattern.
	 *
	 * @param string $ipPattern Exact address or wildcard range.
	 * @param string $noteText  Note content.
	 * @param int    $accountId Staff account filing it.
	 * @return void
	 */
	public function insertNote(string $ipPattern, string $noteText, int $accountId): void {
		$this->insert([
			'ip_pattern' => $ipPattern,
			'is_wildcard' => ipPatternMatcher::isWildcard($ipPattern) ? 1 : 0,
			'note_text' => $noteText,
			'added_by' => $accountId,
		]);
	}

	/**
	 * Insert a note against a browser token hash.
	 *
	 * @param string $tokenHash Visitor token hash as stored on the post.
	 * @param string $noteText  Note content.
	 * @param int    $accountId Staff account filing it.
	 * @return void
	 */
	public function insertTokenNote(string $tokenHash, string $noteText, int $accountId): void {
		$this->insert([
			'ip_pattern' => null,
			'visitor_token_hash' => $tokenHash,
			'is_wildcard' => 0,
			'note_text' => $noteText,
			'added_by' => $accountId,
		]);
	}

	/**
	 * Every note filed against a browser token hash.
	 *
	 * @param string $tokenHash Token hash to look up.
	 * @return array[] Note rows, oldest first.
	 */
	public function getNotesForVisitorToken(string $tokenHash): array {
		return $this->queryAll(
			$this->selectClause()
				. ' WHERE n.visitor_token_hash = :token_hash'
				. ' ORDER BY n.note_submitted ASC, n.id ASC',
			[':token_hash' => $tokenHash]
		);
	}

	/**
	 * Every note filed on exactly this pattern.
	 *
	 * @param string $ipPattern Pattern as it was written.
	 * @return array[] Note rows, oldest first.
	 */
	/**
	 * Notes whose pattern is one of the stored forms of any of the given addresses.
	 *
	 * @param string[] $ipPatterns
	 * @return array[] Note rows, oldest first.
	 */
	public function getNotesForPatterns(array $ipPatterns): array {
		$anonymizer = ipAnonymizer::fromSettings();
		$forms = [];
		foreach ($ipPatterns as $pattern) {
			foreach ($anonymizer->storedForms((string) $pattern) as $form) {
				$forms[$form] = $form;
			}
		}
		if ($forms === []) {
			return [];
		}

		$placeholders = [];
		$params = [];
		foreach (array_values($forms) as $index => $form) {
			$placeholders[] = ":form_{$index}";
			$params[":form_{$index}"] = $form;
		}

		return $this->queryAll(
			$this->selectClause()
				. ' WHERE n.ip_pattern IN (' . implode(', ', $placeholders) . ')'
				. ' ORDER BY n.note_submitted ASC, n.id ASC',
			$params
		);
	}

	public function getNotesForPattern(string $ipPattern): array {
		$forms = ipAnonymizer::fromSettings()->storedForms($ipPattern);
		$placeholders = [];
		$params = [];

		foreach (array_values($forms) as $index => $form) {
			$placeholders[] = ":form_{$index}";
			$params[":form_{$index}"] = $form;
		}

		return $this->queryAll(
			$this->selectClause()
				. ' WHERE n.ip_pattern IN (' . implode(', ', $placeholders) . ')'
				. ' ORDER BY n.note_submitted ASC, n.id ASC',
			$params
		);
	}

	/**
	 * Every wildcard note, for PHP-side matching against an address.
	 *
	 * @return array[] Note rows, oldest first.
	 */
	public function getWildcardNotes(): array {
		return $this->queryAll(
			$this->selectClause() . ' WHERE n.is_wildcard = 1 AND n.ip_pattern IS NOT NULL ORDER BY n.note_submitted ASC, n.id ASC'
		);
	}

	/**
	 * Fetch a note by its primary key.
	 *
	 * @param int $noteId Note primary key.
	 * @return array|false Associative row array, or false if not found.
	 */
	public function getNoteById(int $noteId): false|array {
		return $this->queryOne($this->selectClause() . ' WHERE n.id = :note_id', [':note_id' => $noteId]);
	}

	/**
	 * Check whether the given note belongs to the given account.
	 *
	 * @param int $accountId Account ID to check ownership for.
	 * @param int $noteId    Note ID to verify.
	 * @return bool True if the note is owned by the account.
	 */
	public function noteOwnedByAccount(int $accountId, int $noteId): bool {
		$query = "SELECT 1 FROM {$this->table} WHERE id = :note_id AND added_by = :account_id";
		return $this->queryValue($query, [':note_id' => $noteId, ':account_id' => $accountId]) === 1;
	}

	/**
	 * Update the text of an existing note.
	 *
	 * @param int    $noteId  Note primary key.
	 * @param string $newText Replacement text.
	 * @return void
	 */
	public function editNote(int $noteId, string $newText): void {
		$this->updateWhere(['note_text' => $newText], 'id', $noteId);
	}

	/**
	 * Delete a note by its primary key.
	 *
	 * @param int $noteId Note primary key.
	 * @return void
	 */
	public function deleteNote(int $noteId): void {
		$this->deleteWhere('id', $noteId);
	}

	/**
	 * Return the ID generated by the most recent INSERT.
	 *
	 * @return int|false Last insert ID, or false if unavailable.
	 */
	public function getLastInsertId(): false|int {
		return $this->lastInsertId();
	}
}
