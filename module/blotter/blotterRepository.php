<?php

namespace Kokonotsuba\Modules\blotter;

use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;
use function Kokonotsuba\libraries\pdoNamedPlaceholdersForIn;

class blotterRepository {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $blotterTable,
		private string $accountTable,
	) {}

	public function insertEntry(string $content, ?int $addedBy): void {
		$query = "
			INSERT INTO {$this->blotterTable} (blotter_content, added_by)
			VALUES (:blotter_content, :added_by)
		";

		$params = [
			':blotter_content' => $content,
			':added_by' => $addedBy,
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function deleteEntries(array $entryIds): void {
		if (empty($entryIds)) {
			return;
		}

		$sanitizedIds = array_map('intval', $entryIds);
		$placeholders = pdoPlaceholdersForIn($sanitizedIds);

		$query = "
			DELETE FROM {$this->blotterTable}
			WHERE id IN $placeholders
		";

		$this->databaseConnection->execute($query, $sanitizedIds);
	}

	public function updateEntry(int $entryId, string $content): void {
		$query = "
			UPDATE {$this->blotterTable}
			SET blotter_content = :blotter_content
			WHERE id = :id
		";

		$params = [
			':blotter_content' => $content,
			':id' => $entryId,
		];

		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * @param array<int, string> $entryUpdates
	 * @return int[] IDs that differ from current DB content
	 */
	public function getChangedEntryIds(array $entryUpdates): array {
		if (empty($entryUpdates)) {
			return [];
		}

		$namedIds = pdoNamedPlaceholdersForIn(array_keys($entryUpdates), 'id_');
		$derivedRows = [];
		$params = $namedIds['params'];

		foreach (array_values($entryUpdates) as $index => $content) {
			$idPlaceholder = explode(',', $namedIds['placeholders'])[$index];
			$contentPlaceholder = ':content_' . $index;

			$derivedRows[] = "SELECT {$idPlaceholder} AS id, {$contentPlaceholder} AS blotter_content";
			$params[$contentPlaceholder] = $content;
		}

		$query = "
			SELECT b.id
			FROM {$this->blotterTable} b
			INNER JOIN (
				" . implode("\n\t\t\t\tUNION ALL\n\t\t\t\t", $derivedRows) . "
			) incoming ON incoming.id = b.id
			WHERE b.blotter_content <> incoming.blotter_content
		";

		$rows = $this->databaseConnection->fetchAllAsArray($query, $params);

		return array_values(array_map(
			static fn(array $row): int => (int) ($row['id'] ?? 0),
			$rows
		));
	}

	public function getEntries(?int $limit = null): array {
		$query = "
			SELECT b.id, b.blotter_content, b.added_by, b.date_added, a.username AS added_by_username
			FROM {$this->blotterTable} b
			LEFT JOIN {$this->accountTable} a ON a.id = b.added_by
			ORDER BY date_added DESC, id DESC
		";

		if ($limit !== null) {
			$query .= ' LIMIT ' . (int) $limit;
		}

		return $this->databaseConnection->fetchAllAsClass(
			$query,
			[],
			'\\Kokonotsuba\\Modules\\blotter\\blotterEntry'
		) ?? [];
	}
}