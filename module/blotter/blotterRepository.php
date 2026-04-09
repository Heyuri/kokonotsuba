<?php

namespace Kokonotsuba\Modules\blotter;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoNamedPlaceholdersForIn;

/** Repository for sitewide blotter (news) entries. */
class blotterRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $blotterTable,
		private string $accountTable,
	) {
		parent::__construct($databaseConnection, $blotterTable);
		self::validateTableNames($accountTable);
	}

	/**
	 * Insert a new blotter entry.
	 *
	 * @param string   $content  Blotter text content.
	 * @param int|null $addedBy  Account ID of the staff member adding the entry, or null.
	 * @return void
	 */
	public function insertEntry(string $content, ?int $addedBy): void {
		$this->insert([
			'blotter_content' => $content,
			'added_by' => $addedBy,
		]);
	}

	/**
	 * Delete a set of blotter entries by their primary keys.
	 *
	 * @param array $entryIds Array of integer primary keys to delete.
	 * @return void
	 */
	public function deleteEntries(array $entryIds): void {
		if (empty($entryIds)) {
			return;
		}

		$this->deleteWhereIn('id', array_map('intval', $entryIds));
	}

	/**
	 * Update the text of a single blotter entry.
	 *
	 * @param int    $entryId Entry primary key.
	 * @param string $content New content.
	 * @return void
	 */
	public function updateEntry(int $entryId, string $content): void {
		$this->updateWhere(['blotter_content' => $content], 'id', $entryId);
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
			FROM {$this->table} b
			INNER JOIN (
				" . implode("\n\t\t\t\tUNION ALL\n\t\t\t\t", $derivedRows) . "
			) incoming ON incoming.id = b.id
			WHERE b.blotter_content <> incoming.blotter_content
		";

		$rows = $this->queryAll($query, $params);

		return array_values(array_map(
			static fn(array $row): int => (int) ($row['id'] ?? 0),
			$rows
		));
	}

	/**
	 * Fetch all blotter entries (or a recent subset), ordered newest first.
	 *
	 * @param int|null $limit Maximum number of entries to return, or null for all.
	 * @return blotterEntry[] Array of hydrated blotterEntry objects.
	 */
	public function getEntries(?int $limit = null): array {
		$query = "
			SELECT b.id, b.blotter_content, b.added_by, b.date_added, a.username AS added_by_username
			FROM {$this->table} b
			LEFT JOIN {$this->accountTable} a ON a.id = b.added_by
			ORDER BY date_added DESC, id DESC
		";

		$params = [];
		if ($limit !== null) {
			$this->paginate($query, $params, $limit);
		}

		return $this->queryAllAsClass(
			$query,
			$params,
			'\\Kokonotsuba\\Modules\\blotter\\blotterEntry'
		) ?? [];
	}
}