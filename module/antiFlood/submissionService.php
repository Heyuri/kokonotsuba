<?php

namespace Kokonotsuba\Modules\antiFlood;

/** Service for checking and recording post submission timestamps for flood prevention. */
class submissionService {
	public function __construct(
		private submissionRepository $submissionRepository
	) {}

	/**
	 * Get the last submission timestamp for a board.
	 * 
	 * @param int $boardUID The board UID
	 * @return string|null ISO 8601 timestamp or null
	 */
	public function getLastSubmissionTimeForBoard(int $boardUID): ?string {
		return $this->submissionRepository->getLastSubmissionTimeForBoard($boardUID);
	}

	/**
	 * Record a new submission for a board.
	 * 
	 * @param int $boardUID The board UID
	 * @return bool Success status
	 */
	public function recordSubmission(int $boardUID): bool {
		return $this->submissionRepository->recordSubmission($boardUID);
	}
}
