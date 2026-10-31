<?php

namespace Kokonotsuba\front_pages;

/** One front page page (FAQ, rules, ...), reached by its slug. */
final class frontPage {
	public function __construct(
		public readonly int $id,
		public readonly string $slug,
		public readonly string $title,
		public readonly string $body,
		public readonly bool $isPublished,
		public readonly bool $showInNav,
		public readonly int $navOrder,
		public readonly ?int $authorId,
		public readonly string $createdAt,
		public readonly ?string $updatedAt,
	) {}

	public static function fromRow(array $row): self {
		return new self(
			(int)$row['id'],
			(string)$row['slug'],
			(string)$row['title'],
			(string)$row['body'],
			(bool)$row['is_published'],
			(bool)$row['show_in_nav'],
			(int)$row['nav_order'],
			$row['author_id'] === null ? null : (int)$row['author_id'],
			(string)$row['created_at'],
			$row['updated_at'] ?? null,
		);
	}
}
