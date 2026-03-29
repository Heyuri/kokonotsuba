<?php

namespace Kokonotsuba\Modules\blotter;

use Kokonotsuba\database\transactionManager;

class blotterService {
	public function __construct(
		private blotterRepository $blotterRepository,
		private transactionManager $transactionManager,
	) {}

	public function addEntry(string $content, ?int $addedBy): void {
		$trimmedContent = trim($content);

		if ($trimmedContent === '') {
			return;
		}

		$this->transactionManager->run(function() use ($trimmedContent, $addedBy) {
			$this->blotterRepository->insertEntry($trimmedContent, $addedBy);
		});
	}

	public function deleteEntries(array $entryIds): void {
		if (empty($entryIds)) {
			return;
		}

		$this->transactionManager->run(function() use ($entryIds) {
			$this->blotterRepository->deleteEntries($entryIds);
		});
	}

	/**
	 * @param array<int|string, string> $entryUpdates
	 * @return int[] Updated entry IDs
	 */
	public function editEntries(array $entryUpdates): array {
		if (empty($entryUpdates)) {
			return [];
		}

		$normalizedUpdates = [];

		foreach ($entryUpdates as $entryId => $content) {
			$entryId = (int) $entryId;
			$trimmedContent = trim((string) $content);

			if ($entryId <= 0 || $trimmedContent === '') {
				continue;
			}

			$normalizedUpdates[$entryId] = $trimmedContent;
		}

		if (empty($normalizedUpdates)) {
			return [];
		}

		$changedIds = $this->blotterRepository->getChangedEntryIds($normalizedUpdates);

		if (empty($changedIds)) {
			return [];
		}

		$this->transactionManager->run(function() use ($changedIds, $normalizedUpdates) {
			foreach ($changedIds as $entryId) {
				if (!array_key_exists($entryId, $normalizedUpdates)) {
					continue;
				}

				$content = $normalizedUpdates[$entryId];
				$this->blotterRepository->updateEntry($entryId, $content);
			}
		});

		return $changedIds;
	}

	public function getEntries(?int $limit = null): array {
		if ($limit !== null && $limit < 0) {
			$limit = null;
		}

		return $this->blotterRepository->getEntries($limit);
	}
}