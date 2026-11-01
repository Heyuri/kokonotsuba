<?php

namespace Kokonotsuba\news;

/** One news entry, with its author's username when the account still exists. */
final class newsEntry {
	public function __construct(
		public readonly int $id,
		public readonly string $subject,
		public readonly string $body,
		public readonly string $byline,
		public readonly string $email,
		public readonly ?int $authorId,
		public readonly ?string $authorName,
		public readonly bool $isPublished,
		public readonly string $createdAt,
		public readonly ?string $updatedAt,
	) {}

	public static function fromRow(array $row): self {
		return new self(
			(int)$row['id'],
			(string)$row['subject'],
			(string)$row['body'],
			(string)$row['byline'],
			(string)$row['email'],
			$row['author_id'] === null ? null : (int)$row['author_id'],
			$row['author_name'] ?? null,
			(bool)$row['is_published'],
			(string)$row['created_at'],
			$row['updated_at'] ?? null,
		);
	}

	/** The byline as written, else the author's username. */
	public function displayAuthor(): string {
		return $this->byline !== '' ? $this->byline : (string)$this->authorName;
	}
}
