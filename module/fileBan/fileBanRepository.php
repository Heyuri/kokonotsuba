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

	public function insertBan(string $md5Hash, int $addedBy): void {
		$this->insert([
			'file_md5' => $md5Hash,
			'added_by' => $addedBy,
		]);
	}

	public function getEntries(int $limit, int $offset): array {
		$query = "
			SELECT fb.*, a.username AS added_by_username
			FROM {$this->table} fb
			LEFT JOIN {$this->accountTable} a ON a.id = fb.added_by
			ORDER BY fb.id DESC
		";

		$params = [];
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAll($query, $params);
	}

	public function getTotalEntries(): int {
		return $this->count();
	}

	public function deleteEntries(array $entryIDs): void {
		$this->deleteWhereIn('id', $entryIDs);
	}

	public function hashExists(string $md5Hash): bool {
		return $this->exists('file_md5', $md5Hash);
	}
}
