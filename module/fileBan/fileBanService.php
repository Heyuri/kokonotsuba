<?php

namespace Kokonotsuba\Modules\fileBan;

use Kokonotsuba\database\transactionManager;

class fileBanService {
	public function __construct(
		private fileBanRepository $fileBanRepository,
		private transactionManager $transactionManager
	) {}

	public function findBannedHashes(array $md5Hashes): array {
		$result = $this->fileBanRepository->findBannedHashes($md5Hashes);
		if (!$result) {
			return [];
		}

		return array_column($result, 'file_md5');
	}

	public function addBan(string $md5Hash, int $addedBy): void {
		$this->transactionManager->run(function () use ($md5Hash, $addedBy) {
			// check if hash already exists
			$existing = $this->fileBanRepository->getEntryByHash($md5Hash);
			if ($existing) {
				return;
			}

			$this->fileBanRepository->insertBan($md5Hash, $addedBy);
		});
	}

	public function getEntries(int $limit, int $page): array {
		$offset = $limit * $page;
		$result = $this->fileBanRepository->getEntries($limit, $offset);
		return $result ?: [];
	}

	public function getTotalEntries(): int {
		return $this->fileBanRepository->getTotalEntries();
	}

	public function deleteEntries(array $entryIDs): void {
		$this->transactionManager->run(function () use ($entryIDs) {
			$this->fileBanRepository->deleteEntries($entryIDs);
		});
	}
}
