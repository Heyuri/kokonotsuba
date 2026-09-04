<?php

namespace Kokonotsuba\renderers\post;

use Kokonotsuba\thread\Thread;

use function Kokonotsuba\libraries\_T;

/**
 * The notices drawn on a post about the thread it is in: about to be pruned, or too old to bump.
 */
final class postWarnings {
	/**
	 * @param array $config The board config: STORAGE_LIMIT, MAX_AGE_TIME.
	 * @param int   $now    Unix time the request was made at.
	 */
	public function __construct(
		private readonly array $config,
		private readonly int $now,
	) {}

	/** The storage-limit notice when the thread is about to be pruned. */
	public function sizeLimit(bool $killSensor): string {
		if (!$killSensor || empty($this->config['STORAGE_LIMIT'])) {
			return '';
		}

		return '<div class="warning">' . _T('warn_sizelimit') . '</div>';
	}

	/** The old-thread notice, on the OP only. */
	public function oldThread(?Thread $thread, bool $isOp): string {
		if (!$this->isOldThread($thread, $isOp)) {
			return '';
		}

		return "<div class='warning'>" . _T('warn_oldthread') . "</div>";
	}

	/** Whether the thread has outlived MAX_AGE_TIME, given in hours. Never true off a thread. */
	public function isOldThread(?Thread $thread, bool $isOp): bool {
		if (!$isOp || $thread === null) {
			return false;
		}

		$maxAgeHours = (int)($this->config['MAX_AGE_TIME'] ?? 0);

		if ($maxAgeHours <= 0) {
			return false;
		}

		return $this->now - strtotime($thread->getCreatedTime()) > $maxAgeHours * 3600;
	}
}
