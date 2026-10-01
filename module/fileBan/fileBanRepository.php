<?php

namespace Kokonotsuba\Modules\fileBan;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class fileBanRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $fileBanTable,
		private string $accountTable
	) {
		parent::__construct($databaseConnection, $fileBanTable);
		self::validateTableNames($accountTable);
	}

	public function findBannedHashes(array $md5Hashes): array {
		return $this->pluckWhereIn('file_md5', 'file_md5', $md5Hashes);
	}

	/** @return array<int, array> Ban rows matching any of the given hashes. */
	public function findBannedEntries(array $md5Hashes): array {
		return $this->findAllWhereIn('file_md5', $md5Hashes);
	}

	public function insertBan(string $md5Hash, int $addedBy): void {
		$this->insert([
			'file_md5' => $md5Hash,
			'added_by' => $addedBy,
		]);
	}

	public function getEntries(int $limit, int $offset, ?int $entryId = null): array {
		$where = $entryId === null ? '' : 'WHERE fb.id = :id';
		$query = "
			SELECT fb.*, a.username AS added_by_username
			FROM {$this->table} fb
			LEFT JOIN {$this->accountTable} a ON a.id = fb.added_by
			{$where}
			ORDER BY fb.id DESC
		";

		$params = $entryId === null ? [] : [':id' => $entryId];
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAll($query, $params);
	}

	public function getTotalEntries(?int $entryId = null): int {
		return $entryId === null ? $this->count() : $this->count('id = :id', [':id' => $entryId]);
	}

	public function deleteEntries(array $entryIDs): void {
		$this->deleteWhereIn('id', $entryIDs);
	}

	public function hashExists(string $md5Hash): bool {
		return $this->exists('file_md5', $md5Hash);
	}
}
