<?php

namespace Kokonotsuba\news;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\SqlExpression;

/** Front page news entries, newest first. */
class newsRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $newsTable,
		private string $accountTable,
	) {
		parent::__construct($databaseConnection, $newsTable);
		self::validateTableNames($accountTable);
	}

	/**
	 * @param bool $includeDrafts Also list unpublished entries.
	 * @return newsEntry[]
	 */
	public function listEntries(bool $includeDrafts, int $limit, int $offset): array {
		$query = $this->selectSql() . ($includeDrafts ? '' : ' WHERE n.is_published = 1')
			. ' ORDER BY n.created_at DESC, n.id DESC';
		$params = [];
		$this->paginate($query, $params, $limit, $offset);

		return array_map(newsEntry::fromRow(...), $this->queryAll($query, $params));
	}

	public function countEntries(bool $includeDrafts): int {
		return $this->count($includeDrafts ? '' : 'is_published = 1');
	}

	/** Whether an entry with this subject was posted at this time, so an import can skip it. */
	public function hasEntry(string $subject, string $createdAt): bool {
		return $this->count('subject = :subject AND created_at = :created_at', [':subject' => $subject, ':created_at' => $createdAt]) > 0;
	}

	public function findEntry(int $id): ?newsEntry {
		$row = $this->queryOne($this->selectSql() . ' WHERE n.id = :id', [':id' => $id]);

		return $row ? newsEntry::fromRow($row) : null;
	}

	/**
	 * @param ?string $createdAt 'Y-m-d H:i:s', or null for now
	 * @return int the new entry's id
	 */
	public function insertEntry(string $subject, string $body, string $byline, string $email, ?int $authorId, bool $isPublished, ?string $createdAt = null): int {
		$this->insert([
			'subject' => $subject,
			'body' => $body,
			'byline' => $byline,
			'email' => $email,
			'author_id' => $authorId,
			'is_published' => (int)$isPublished,
		] + ($createdAt !== null ? ['created_at' => $createdAt] : []));

		return (int)$this->lastInsertId();
	}

	public function updateEntry(int $id, string $subject, string $body, string $byline, string $email, bool $isPublished): void {
		$this->updateWhere([
			'subject' => $subject,
			'body' => $body,
			'byline' => $byline,
			'email' => $email,
			'is_published' => (int)$isPublished,
			'updated_at' => SqlExpression::now(),
		], 'id', $id);
	}

	public function deleteEntry(int $id): void {
		$this->deleteWhere('id', $id);
	}

	private function selectSql(): string {
		return "SELECT n.*, a.username AS author_name FROM {$this->table} n"
			. " LEFT JOIN {$this->accountTable} a ON a.id = n.author_id";
	}
}
