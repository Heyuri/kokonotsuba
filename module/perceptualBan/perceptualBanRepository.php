<?php

namespace Kokonotsuba\Modules\perceptualBan;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

class perceptualBanRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $perceptualBanTable,
		private string $accountTable
	) {
		parent::__construct($databaseConnection, $perceptualBanTable);
	}

	/**
	 * Find any banned hashes within a given hamming distance threshold of the provided hash.
	 * Uses MySQL BIT_COUNT(XOR) for efficient comparison.
	 *
	 * @param int $hashInt Integer representation of the perceptual hash
	 * @param int $threshold Maximum hamming distance to consider a match
	 * @return array Matching rows sorted by distance
	 */
	public function findMatchingBans(int $hashInt, int $threshold): array {
		$query = "
			SELECT id, phash, BIT_COUNT(phash ^ :hash) AS distance
			FROM {$this->table}
			WHERE BIT_COUNT(phash ^ :hash2) <= :threshold
			ORDER BY distance ASC
			LIMIT 1
		";

		$params = [
			':hash' => $hashInt,
			':hash2' => $hashInt,
			':threshold' => $threshold,
		];

		return $this->queryAll($query, $params);
	}

	/**
	 * Insert a new perceptual ban row.
	 *
	 * @param int $hashInt Integer representation of the perceptual hash
	 * @param string $hashHex 16-character hex representation stored for display
	 * @param int $addedBy Account ID of the staff member adding the ban
	 */
	public function insertBan(int $hashInt, string $hashHex, int $addedBy): void {
		$this->insert([
			'phash' => $hashInt,
			'phash_hex' => $hashHex,
			'added_by' => $addedBy,
		]);
	}

	/**
	 * Get paginated ban entries with the adding user's username.
	 *
	 * @param int $limit Maximum rows to return
	 * @param int $offset Row offset for pagination
	 * @return array Ban rows ordered newest-first
	 */
	public function getEntries(int $limit, int $offset): array {
		$query = "
			SELECT pb.*, a.username AS added_by_username
			FROM {$this->table} pb
			LEFT JOIN {$this->accountTable} a ON a.id = pb.added_by
			ORDER BY pb.id DESC
		";

		$params = [];
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAll($query, $params);
	}

	/**
	 * Get the total number of perceptual ban rows.
	 *
	 * @return int Total count
	 */
	public function getTotalEntries(): int {
		return $this->count();
	}

	/**
	 * Delete perceptual ban rows by their IDs.
	 *
	 * @param array<int> $entryIDs List of row IDs to delete
	 */
	public function deleteEntries(array $entryIDs): void {
		$this->deleteWhereIn('id', $entryIDs);
	}

	/**
	 * Check whether a ban entry exists for the exact perceptual hash.
	 *
	 * @param int $hashInt Integer representation of the perceptual hash
	 * @return bool True if the hash is already banned
	 */
	public function hashExists(int $hashInt): bool {
		return $this->exists('phash', $hashInt);
	}
}
