<?php

namespace Kokonotsuba\Modules\fileBan;

use Kokonotsuba\database\transactionManager;

class fileBanService {
	public function __construct(
		private fileBanRepository $fileBanRepository,
		private transactionManager $transactionManager
	) {}

	public function findBannedHashes(array $md5Hashes): array {
		return $this->fileBanRepository->findBannedHashes($md5Hashes);
	}

	/** First ban entry matching the hashes, taken in the order given. */
	public function findFirstBannedEntry(array $md5Hashes): ?array {
		// keyed lowercase, since the column compares case-insensitively
		$entries = [];
		foreach ($this->fileBanRepository->findBannedEntries($md5Hashes) as $entry) {
			$entries[strtolower($entry['file_md5'])] = $entry;
		}

		foreach ($md5Hashes as $md5) {
			if (isset($entries[strtolower($md5)])) {
				return $entries[strtolower($md5)];
			}
		}

		return null;
	}

	public function addBan(string $md5Hash, int $addedBy): void {
		$this->transactionManager->run(function () use ($md5Hash, $addedBy) {
			if ($this->fileBanRepository->hashExists($md5Hash)) {
				return;
			}

			$this->fileBanRepository->insertBan($md5Hash, $addedBy);
		});
	}

	public function getEntries(int $limit, int $page, ?int $entryId = null): array {
		$offset = $limit * $page;
		return $this->fileBanRepository->getEntries($limit, $offset, $entryId);
	}

	public function getTotalEntries(?int $entryId = null): int {
		return $this->fileBanRepository->getTotalEntries($entryId);
	}

	public function deleteEntries(array $entryIDs): void {
		$this->transactionManager->run(function () use ($entryIDs) {
			$this->fileBanRepository->deleteEntries($entryIDs);
		});
	}
}
