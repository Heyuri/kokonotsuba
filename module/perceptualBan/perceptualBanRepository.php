<?php

namespace Kokonotsuba\Modules\perceptualBan;

use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

class perceptualBanRepository {
	public function __construct(
		private databaseConnection $databaseConnection,
		private string $perceptualBanTable,
		private string $accountTable
	) {}

	/**
	 * Find any banned hashes within a given hamming distance threshold of the provided hash.
	 * Uses MySQL BIT_COUNT(XOR) for efficient comparison.
	 *
	 * @param int $hashInt Integer representation of the perceptual hash
	 * @param int $threshold Maximum hamming distance to consider a match
	 * @return false|array<int, array> Matching rows sorted by distance, or false if none
	 */
	public function findMatchingBans(int $hashInt, int $threshold): false|array {
		$query = "
			SELECT id, phash, BIT_COUNT(phash ^ :hash) AS distance
			FROM {$this->perceptualBanTable}
			WHERE BIT_COUNT(phash ^ :hash2) <= :threshold
			ORDER BY distance ASC
			LIMIT 1
		";

		$params = [
			':hash' => $hashInt,
			':hash2' => $hashInt,
			':threshold' => $threshold,
		];

		return $this->databaseConnection->fetchAllAsArray($query, $params);
	}

	/**
	 * Insert a new perceptual ban row.
	 *
	 * @param int $hashInt Integer representation of the perceptual hash
	 * @param string $hashHex 16-character hex representation stored for display
	 * @param int $addedBy Account ID of the staff member adding the ban
	 */
	public function insertBan(int $hashInt, string $hashHex, int $addedBy): void {
		$query = "
			INSERT INTO {$this->perceptualBanTable} (phash, phash_hex, added_by)
			VALUES (:phash, :phash_hex, :added_by)
		";

		$params = [
			':phash' => $hashInt,
			':phash_hex' => $hashHex,
			':added_by' => $addedBy,
		];

		$this->databaseConnection->execute($query, $params);
	}

	/**
	 * Get paginated ban entries with the adding user's username.
	 *
	 * @param int $limit Maximum rows to return
	 * @param int $offset Row offset for pagination
	 * @return false|array<int, array> Ban rows ordered newest-first, or false if none
	 */
	public function getEntries(int $limit, int $offset): false|array {
		$query = "
			SELECT pb.*, a.username AS added_by_username
			FROM {$this->perceptualBanTable} pb
			LEFT JOIN {$this->accountTable} a ON a.id = pb.added_by
			ORDER BY pb.id DESC
			LIMIT {$limit} OFFSET {$offset}
		";

		return $this->databaseConnection->fetchAllAsArray($query);
	}

	/**
	 * Get the total number of perceptual ban rows.
	 *
	 * @return int Total count
	 */
	public function getTotalEntries(): int {
		$query = "SELECT COUNT(*) FROM {$this->perceptualBanTable}";
		$row = $this->databaseConnection->fetchValue($query);
		return (int) ($row ?? 0);
	}

	/**
	 * Delete perceptual ban rows by their IDs.
	 *
	 * @param array<int> $entryIDs List of row IDs to delete
	 */
	public function deleteEntries(array $entryIDs): void {
		if (empty($entryIDs)) {
			return;
		}

		$placeholders = pdoPlaceholdersForIn($entryIDs);

		$query = "
			DELETE FROM {$this->perceptualBanTable}
			WHERE id IN {$placeholders}
		";

		$this->databaseConnection->execute($query, $entryIDs);
	}

	/**
	 * Look up a single ban entry by its exact perceptual hash.
	 *
	 * @param int $hashInt Integer representation of the perceptual hash
	 * @return false|array The ban row with added_by_username, or false if not found
	 */
	public function getEntryByHash(int $hashInt): false|array {
		$query = "
			SELECT pb.*, a.username AS added_by_username
			FROM {$this->perceptualBanTable} pb
			LEFT JOIN {$this->accountTable} a ON a.id = pb.added_by
			WHERE pb.phash = :phash
		";

		$params = [':phash' => $hashInt];

		return $this->databaseConnection->fetchOne($query, $params);
	}
}
