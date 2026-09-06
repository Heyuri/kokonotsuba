<?php

namespace Kokonotsuba\Modules\edit;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/**
 * A post's edit history: what it said before each edit.
 *
 * Table structure
 * id INT AUTO_INCREMENT PRIMARY KEY,
 * post_uid INT NOT NULL,
 * boardUID INT NOT NULL,
 * edited_by INT NULL,          -- NULL when the poster edited it themselves
 * edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
 * name, email, sub, tag VARCHAR NULL,
 * com TEXT NULL
 */
class postRevisionRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $revisionTable,
		private readonly string $accountTable
	) {
		parent::__construct($databaseConnection, $revisionTable);
		self::validateTableName($this->accountTable);
	}

	/**
	 * Record what a post said before an edit.
	 *
	 * @param array<string, mixed> $values The post's name, email, sub, com and tag as stored.
	 * @return int The new revision's id.
	 */
	public function insertRevision(int $postUid, int $boardUid, ?int $editedBy, array $values): int {
		$this->insert([
			'post_uid' => $postUid,
			'boardUID' => $boardUid,
			'edited_by' => $editedBy,
			'name' => $values['name'] ?? null,
			'email' => $values['email'] ?? null,
			'sub' => $values['sub'] ?? null,
			'com' => $values['com'] ?? null,
			'tag' => $values['tag'] ?? null,
		]);

		return (int)$this->lastInsertId();
	}

	/**
	 * Every revision of a post, newest first, carrying the editor's name.
	 *
	 * @return array[] Revision rows.
	 */
	public function getRevisionsForPost(int $postUid): array {
		return $this->queryAll(
			"SELECT r.*, a.username AS edited_by_username
				FROM {$this->table} r
				LEFT JOIN {$this->accountTable} a ON a.id = r.edited_by
				WHERE r.post_uid = :post_uid
				ORDER BY r.edited_at DESC, r.id DESC",
			[':post_uid' => $postUid]
		);
	}

	/**
	 * One revision by its primary key.
	 *
	 * @return array|false The row, or false when there is no such revision.
	 */
	public function getRevisionById(int $revisionId): array|false {
		return $this->queryOne(
			"SELECT r.*, a.username AS edited_by_username
				FROM {$this->table} r
				LEFT JOIN {$this->accountTable} a ON a.id = r.edited_by
				WHERE r.id = :id",
			[':id' => $revisionId]
		);
	}

	/** How many revisions a post has, for deciding whether to offer the history at all. */
	public function countRevisionsForPost(int $postUid): int {
		return $this->countBy('post_uid', $postUid);
	}
}
