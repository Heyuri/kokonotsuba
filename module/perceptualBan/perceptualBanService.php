<?php

namespace Kokonotsuba\Modules\perceptualBan;

use Kokonotsuba\database\transactionManager;

class perceptualBanService {
	public function __construct(
		private perceptualBanRepository $perceptualBanRepository,
		private perceptualHasher $perceptualHasher,
		private transactionManager $transactionManager
	) {}

	/**
	 * Check if a file's perceptual hash matches any banned hash within the threshold.
	 *
	 * @param string $filePath Path to the image file on disk
	 * @param int $threshold Maximum hamming distance to consider a match
	 * @return bool True if the file matches a banned perceptual hash
	 */
	public function isPerceptuallyBanned(string $filePath, int $threshold): bool {
		$hashHex = $this->perceptualHasher->computeHash($filePath);
		if ($hashHex === null) {
			return false;
		}

		$hashInt = $this->perceptualHasher->hexToInt($hashHex);
		$matches = $this->perceptualBanRepository->findMatchingBans($hashInt, $threshold);

		return !empty($matches);
	}

	/**
	 * Check if an animated file's perceptual hash (from a random frame) matches any banned hash.
	 *
	 * @param string $filePath Path to the animated file (GIF/video) on disk
	 * @param int $threshold Maximum hamming distance to consider a match
	 * @return bool True if the file matches a banned perceptual hash
	 */
	public function isPerceptuallyBannedAnimated(string $filePath, int $threshold): bool {
		$hashHex = $this->perceptualHasher->computeHashFromAnimated($filePath);
		if ($hashHex === null) {
			return false;
		}

		$hashInt = $this->perceptualHasher->hexToInt($hashHex);
		$matches = $this->perceptualBanRepository->findMatchingBans($hashInt, $threshold);

		return !empty($matches);
	}

	/**
	 * Check if a given hex hash matches any banned hash within the threshold.
	 *
	 * @param string $hashHex 16-character hex perceptual hash
	 * @param int $threshold Maximum hamming distance to consider a match
	 * @return bool True if the hash matches a banned entry
	 */
	public function isHashBanned(string $hashHex, int $threshold): bool {
		$hashInt = $this->perceptualHasher->hexToInt($hashHex);
		$matches = $this->perceptualBanRepository->findMatchingBans($hashInt, $threshold);

		return !empty($matches);
	}

	/**
	 * Add a perceptual hash to the ban list.
	 *
	 * @param string $hashHex 16-character hex perceptual hash
	 * @param int $addedBy Account ID of the staff member adding the ban
	 */
	public function addBan(string $hashHex, int $addedBy): void {
		$this->transactionManager->run(function () use ($hashHex, $addedBy) {
			$hashInt = $this->perceptualHasher->hexToInt($hashHex);

			if ($this->perceptualBanRepository->hashExists($hashInt)) {
				return;
			}

			$this->perceptualBanRepository->insertBan($hashInt, $hashHex, $addedBy);
		});
	}

	/**
	 * Compute a perceptual hash for a file.
	 *
	 * @param string $filePath Path to the image file on disk
	 * @return string|null 16-character hex hash, or null if the file cannot be processed
	 */
	public function computeHashForFile(string $filePath): ?string {
		return $this->perceptualHasher->computeHash($filePath);
	}

	/**
	 * Get a paginated list of perceptual ban entries.
	 *
	 * @param int $limit Maximum entries per page
	 * @param int $page Zero-based page number
	 * @return array<int, array> List of ban entries
	 */
	public function getEntries(int $limit, int $page): array {
		$offset = $limit * $page;
		return $this->perceptualBanRepository->getEntries($limit, $offset);
	}

	/**
	 * Get the total number of perceptual ban entries.
	 *
	 * @return int Total count
	 */
	public function getTotalEntries(): int {
		return $this->perceptualBanRepository->getTotalEntries();
	}

	/**
	 * Delete perceptual ban entries by their IDs.
	 *
	 * @param array<int> $entryIDs List of entry IDs to remove
	 */
	public function deleteEntries(array $entryIDs): void {
		$this->transactionManager->run(function () use ($entryIDs) {
			$this->perceptualBanRepository->deleteEntries($entryIDs);
		});
	}
}
