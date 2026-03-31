<?php

namespace Kokonotsuba\Modules\fileBan;

use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

class fileBanRepository {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $fileBanTable,
		private string $accountTable
	) {}

	public function findBannedHashes(array $md5Hashes): false|array {
		if (empty($md5Hashes)) {
			return false;
		}

		$placeholders = pdoPlaceholdersForIn($md5Hashes);

		$query = "
			SELECT file_md5
			FROM {$this->fileBanTable}
			WHERE file_md5 IN {$placeholders}
		";

		return $this->databaseConnection->fetchAllAsArray($query, $md5Hashes);
	}

	public function insertBan(string $md5Hash, int $addedBy): void {
		$query = "
			INSERT INTO {$this->fileBanTable} (file_md5, added_by)
			VALUES (:file_md5, :added_by)
		";

		$params = [
			':file_md5' => $md5Hash,
			':added_by' => $addedBy,
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function getEntries(int $limit, int $offset): false|array {
		$query = "
			SELECT fb.*, a.username AS added_by_username
			FROM {$this->fileBanTable} fb
			LEFT JOIN {$this->accountTable} a ON a.id = fb.added_by
			ORDER BY fb.id DESC
			LIMIT {$limit} OFFSET {$offset}
		";

		return $this->databaseConnection->fetchAllAsArray($query);
	}

	public function getTotalEntries(): int {
		$query = "SELECT COUNT(*) FROM {$this->fileBanTable}";
		$row = $this->databaseConnection->fetchValue($query);
		return (int) ($row ?? 0);
	}

	public function deleteEntries(array $entryIDs): void {
		if (empty($entryIDs)) {
			return;
		}

		$placeholders = pdoPlaceholdersForIn($entryIDs);

		$query = "
			DELETE FROM {$this->fileBanTable}
			WHERE id IN {$placeholders}
		";

		$this->databaseConnection->execute($query, $entryIDs);
	}

	public function getEntryByHash(string $md5Hash): false|array {
		$query = "
			SELECT fb.*, a.username AS added_by_username
			FROM {$this->fileBanTable} fb
			LEFT JOIN {$this->accountTable} a ON a.id = fb.added_by
			WHERE fb.file_md5 = :file_md5
		";

		$params = [':file_md5' => $md5Hash];

		return $this->databaseConnection->fetchOne($query, $params);
	}
}
