<?php

namespace Kokonotsuba\Modules\sticky;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

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
