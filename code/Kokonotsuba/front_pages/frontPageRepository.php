<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\SqlExpression;

/** Front page pages, in navigation order. */
class frontPageRepository extends baseRepository {
	public function __construct(databaseConnection $databaseConnection, string $frontPagesTable) {
		parent::__construct($databaseConnection, $frontPagesTable);
	}

	/** @return frontPage[] published pages shown in the navigation */
	public function listNavPages(): array {
		return $this->listWhere('is_published = 1 AND show_in_nav = 1');
	}

	/** @return frontPage[] every page, drafts included */
	public function listAllPages(): array {
		return $this->listWhere('');
	}

	public function findBySlug(string $slug): ?frontPage {
		$row = $this->queryOne("SELECT * FROM {$this->table} WHERE slug = :slug", [':slug' => $slug]);

		return $row ? frontPage::fromRow($row) : null;
	}

	public function findById(int $id): ?frontPage {
		$row = $this->queryOne("SELECT * FROM {$this->table} WHERE id = :id", [':id' => $id]);

		return $row ? frontPage::fromRow($row) : null;
	}

	/** Whether another page already has this slug. */
	public function slugTaken(string $slug, ?int $exceptId = null): bool {
		return $this->count('slug = :slug AND id <> :id', [':slug' => $slug, ':id' => $exceptId ?? 0]) > 0;
	}

	/** @return int the new page's id */
	public function insertPage(string $slug, string $title, string $body, bool $isPublished, bool $showInNav, int $navOrder, ?int $authorId): int {
		$this->insert([
			'slug' => $slug,
			'title' => $title,
			'body' => $body,
			'is_published' => (int)$isPublished,
			'show_in_nav' => (int)$showInNav,
			'nav_order' => $navOrder,
			'author_id' => $authorId,
		]);

		return (int)$this->lastInsertId();
	}

	public function updatePage(int $id, string $slug, string $title, string $body, bool $isPublished, bool $showInNav, int $navOrder): void {
		$this->updateWhere([
			'slug' => $slug,
			'title' => $title,
			'body' => $body,
			'is_published' => (int)$isPublished,
			'show_in_nav' => (int)$showInNav,
			'nav_order' => $navOrder,
			'updated_at' => SqlExpression::now(),
		], 'id', $id);
	}

	public function deletePage(int $id): void {
		$this->deleteWhere('id', $id);
	}

	/** @return frontPage[] */
	private function listWhere(string $where): array {
		$query = "SELECT * FROM {$this->table}" . ($where !== '' ? " WHERE {$where}" : '') . ' ORDER BY nav_order ASC, title ASC, id ASC';

		return array_map(frontPage::fromRow(...), $this->queryAll($query));
	}
}
