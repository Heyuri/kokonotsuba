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

	public function addBan(string $md5Hash, int $addedBy): void {
		$this->transactionManager->run(function () use ($md5Hash, $addedBy) {
			if ($this->fileBanRepository->hashExists($md5Hash)) {
				return;
			}

			$this->fileBanRepository->insertBan($md5Hash, $addedBy);
		});
	}

	public function getEntries(int $limit, int $page): array {
		$offset = $limit * $page;
		return $this->fileBanRepository->getEntries($limit, $offset);
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
