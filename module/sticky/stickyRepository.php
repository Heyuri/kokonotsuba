<?php

namespace Kokonotsuba\Modules\sticky;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

class stickyRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $threadTable,
	) {
		parent::__construct($databaseConnection, $threadTable);
	}

	public function isSticky(string $thread_uid): bool {
		return (bool) $this->pluck('is_sticky', 'thread_uid', $thread_uid);
	}

	public function stickyThread(string $thread_uid): void {
		$this->updateWhere(['is_sticky' => true], 'thread_uid', $thread_uid);
	}

	public function unstickyThread(string $thread_uid): void {
		$this->updateWhere(['is_sticky' => false], 'thread_uid', $thread_uid);
	}

	/**
	 * Set the sticky flag on many threads with one statement.
	 *
	 * @param string[] $threadUids
	 */
	public function setStickyForThreads(array $threadUids, bool $isSticky): void {
		if (!$threadUids) {
			return;
		}

		$this->updateWhereIn(['is_sticky' => $isSticky], 'thread_uid', array_values($threadUids));
	}

	/**
	 * Which of the given threads are currently stickied.
	 *
	 * @param string[] $threadUids
	 * @return string[] Thread UIDs that are sticky.
	 */
	public function getStickyThreadUids(array $threadUids): array {
		if (!$threadUids) {
			return [];
		}

		$threadUids = array_values($threadUids);
		$inClause = pdoPlaceholdersForIn($threadUids);

		$rows = $this->queryAllAsIndexArray(
			"SELECT thread_uid FROM {$this->table} WHERE thread_uid IN $inClause AND is_sticky = 1",
			$threadUids
		);

		return $rows ? array_merge(...$rows) : [];
	}

	public function toggleSticky(string $thread_uid): bool {
		$isSticky = $this->isSticky($thread_uid);

		if ($isSticky) {
			$this->unstickyThread($thread_uid);
		} else {
			$this->stickyThread($thread_uid);
		}

		return !$isSticky;
	}
}
