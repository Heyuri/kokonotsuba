<?php

namespace Kokonotsuba\Modules\sideNav;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Side nav groups and their links. */
class sideNavRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $groupTable,
		private readonly string $linkTable,
	) {
		parent::__construct($databaseConnection, $groupTable);
		self::validateTableNames($linkTable);
	}

	/** @return array<int, array{id: int, title: string, kind: string, sort_order: int}> */
	public function getGroups(): array {
		return $this->queryAll("SELECT id, title, kind, sort_order FROM {$this->table} ORDER BY sort_order, id");
	}

	/** @return array<int, array{id: int, label: string, url: string, source: ?string, hidden: int, group_id: int, sort_order: int}> */
	public function getLinks(): array {
		return $this->queryAll("SELECT id, label, url, source, hidden, group_id, sort_order FROM {$this->linkTable} ORDER BY sort_order, id");
	}

	/** @return array<int, array> groups in order, each with its links in order under 'links' */
	public function getLayout(): array {
		$layout = [];
		foreach ($this->getGroups() as $group) {
			$group['links'] = [];
			$layout[(int) $group['id']] = $group;
		}
		foreach ($this->getLinks() as $link) {
			$layout[(int) $link['group_id']]['links'][] = $link;
		}
		return array_values($layout);
	}

	public function addGroup(string $title, string $kind, int $sortOrder): int {
		$this->insert(['title' => $title, 'kind' => $kind, 'sort_order' => $sortOrder]);
		return (int) $this->lastInsertId();
	}

	public function updateGroup(int $id, string $title, int $sortOrder): void {
		$this->updateWhere(['title' => $title, 'sort_order' => $sortOrder], 'id', $id);
	}

	/** @param int[] $ids */
	public function deleteGroups(array $ids): void {
		if ($ids) {
			$this->deleteWhereIn('id', $ids);
		}
	}

	/** @param array{label: string, url: string, source: ?string, hidden: int} $link */
	public function addLink(array $link, int $groupId, int $sortOrder): void {
		$this->query(
			"INSERT INTO {$this->linkTable} (label, url, source, hidden, group_id, sort_order) VALUES (:label, :url, :source, :hidden, :group_id, :sort_order)",
			$this->linkParams($link, $groupId, $sortOrder)
		);
	}

	/** @param array{label: string, url: string, source: ?string, hidden: int} $link */
	public function updateLink(int $id, array $link, int $groupId, int $sortOrder): void {
		$this->query(
			"UPDATE {$this->linkTable} SET label = :label, url = :url, source = :source, hidden = :hidden, group_id = :group_id, sort_order = :sort_order WHERE id = :id",
			$this->linkParams($link, $groupId, $sortOrder) + [':id' => $id]
		);
	}

	private function linkParams(array $link, int $groupId, int $sortOrder): array {
		return [
			':label' => $link['label'],
			':url' => $link['url'],
			':source' => $link['source'],
			':hidden' => (int) $link['hidden'],
			':group_id' => $groupId,
			':sort_order' => $sortOrder,
		];
	}

	/** @param int[] $ids */
	public function deleteLinks(array $ids): void {
		if (!$ids) {
			return;
		}
		$in = $this->buildInClause($ids);
		$this->query("DELETE FROM {$this->linkTable} WHERE id IN {$in}", array_values($ids));
	}
}
