<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\database\transactionManager;
use Kokonotsuba\database\TransactionalTrait;
use Kokonotsuba\post\deletion\postDeletionService;

/**
 * Report business rules: filing, moderator decisions, and read receipts.
 *
 * Approving is the only action with a side effect outside the report tables — it usually deletes
 * the reported post — so it runs in a transaction alongside the status update.
 */
class reportService {
	use TransactionalTrait;

	public function __construct(
		private readonly reportRepository $reportRepository,
		private readonly postDeletionService $postDeletionService,
		private readonly transactionManager $transactionManager,
	) {}

	/**
	 * File a report against a post.
	 *
	 * @param int         $postUid  UID of the post being reported.
	 * @param int         $boardUid UID of the board the post lives on.
	 * @param string      $ip       Reporter's IP address.
	 * @param string|null $reason   Optional free-text reason.
	 * @return int The new report id.
	 */
	public function fileReport(int $postUid, int $boardUid, string $ip, ?string $reason): int {
		return $this->reportRepository->insertReport($postUid, $boardUid, $ip, $reason);
	}

	/** Whether this IP already has an open report on the post. */
	public function hasOpenReport(int $postUid, string $ip): bool {
		return $this->reportRepository->hasPendingReportFromIp($postUid, $ip);
	}

	/**
	 * Approve reports, and by default delete the posts they point at.
	 *
	 * Any other pending report on the same post is approved too: agreeing with one report on a
	 * post settles the rest of them, and every reporter deserves to see their report was acted
	 * on rather than left in the queue.
	 *
	 * @param array       $reportIds     Report ids the moderator selected.
	 * @param int|null    $accountId     Staff account performing the action.
	 * @param string|null $publicReason  Reason visible to the reporter.
	 * @param string|null $privateReason Reason visible only to staff.
	 * @param bool        $deletePost    Whether to delete the posts as well as close the reports.
	 * @return int Number of posts whose reports were approved.
	 */
	public function approveReports(
		array $reportIds,
		?int $accountId,
		?string $publicReason,
		?string $privateReason,
		bool $deletePost = true
	): int {
		$reports = $this->loadReports($reportIds);

		if (empty($reports)) {
			return 0;
		}

		$postUids = array_values(array_unique(array_map(
			static fn(array $report): int => (int) $report['post_uid'],
			$reports
		)));

		// Pull in every other pending report on the same posts so none are left orphaned.
		$idsToAction = array_map(static fn(array $report): int => (int) $report['report_id'], $reports);
		foreach ($postUids as $postUid) {
			$idsToAction = array_merge($idsToAction, $this->reportRepository->getPendingReportIdsForPost($postUid));
		}
		$idsToAction = array_values(array_unique($idsToAction));

		$this->inTransaction(function () use ($idsToAction, $postUids, $accountId, $publicReason, $privateReason, $deletePost) {
			$this->reportRepository->actionReports(
				$idsToAction,
				reportStatus::APPROVED->value,
				$accountId,
				$publicReason,
				$privateReason
			);

			// Approving normally means the report was right and the post goes, but a moderator
			// can agree with a report and still leave the post up — a warning, say.
			if ($deletePost) {
				$this->postDeletionService->removePosts($postUids, $accountId);
			}
		});

		return count($postUids);
	}

	/**
	 * Dismiss reports without touching the posts they point at.
	 *
	 * @return int Number of reports dismissed.
	 */
	public function dismissReports(
		array $reportIds,
		?int $accountId,
		?string $publicReason,
		?string $privateReason
	): int {
		$reports = $this->loadReports($reportIds);

		if (empty($reports)) {
			return 0;
		}

		$ids = array_map(static fn(array $report): int => (int) $report['report_id'], $reports);

		$this->reportRepository->actionReports(
			$ids,
			reportStatus::DISMISSED->value,
			$accountId,
			$publicReason,
			$privateReason
		);

		return count($ids);
	}

	/**
	 * Dismiss every pending report on a post in one go.
	 *
	 * @return int Number of reports cleared.
	 */
	public function clearReportsForPost(
		int $postUid,
		?int $accountId,
		?string $publicReason,
		?string $privateReason
	): int {
		$pendingIds = $this->reportRepository->getPendingReportIdsForPost($postUid);

		if (empty($pendingIds)) {
			return 0;
		}

		$this->reportRepository->actionReports(
			$pendingIds,
			reportStatus::DISMISSED->value,
			$accountId,
			$publicReason,
			$privateReason
		);

		return count($pendingIds);
	}

	/**
	 * Close the pending reports on posts that have just been deleted.
	 *
	 * A post can be deleted from anywhere — the mod tools, a thread cascade, the poster's own
	 * delete form — and once it is gone its reports have effectively been granted, so they are
	 * marked approved rather than left in the queue pointing at nothing.
	 *
	 * @param array       $postUids  Posts that were deleted.
	 * @param int|null    $accountId Account that performed the deletion, if any.
	 * @param string|null $publicReason Reason shown to the reporters.
	 * @return int Number of reports closed.
	 */
	public function approveReportsForDeletedPosts(array $postUids, ?int $accountId, ?string $publicReason): int {
		$pendingIds = [];

		foreach (array_unique(array_map('intval', $postUids)) as $postUid) {
			$pendingIds = array_merge($pendingIds, $this->reportRepository->getPendingReportIdsForPost($postUid));
		}

		if (empty($pendingIds)) {
			return 0;
		}

		$this->reportRepository->actionReports(
			$pendingIds,
			reportStatus::APPROVED->value,
			$accountId,
			$publicReason,
			null
		);

		return count($pendingIds);
	}

	/**
	 * Dismiss every pending report filed from one IP.
	 *
	 * @return int Number of reports cleared.
	 */
	public function clearReportsForIp(
		string $ip,
		?int $accountId,
		?string $publicReason,
		?string $privateReason
	): int {
		$pendingIds = $this->reportRepository->getPendingReportIdsForIp($ip);

		if (empty($pendingIds)) {
			return 0;
		}

		$this->reportRepository->actionReports(
			$pendingIds,
			reportStatus::DISMISSED->value,
			$accountId,
			$publicReason,
			$privateReason
		);

		return count($pendingIds);
	}

	/**
	 * Load the given report ids, skipping ids that don't exist or were already actioned.
	 *
	 * @return array Report rows still eligible for a decision.
	 */
	private function loadReports(array $reportIds): array {
		$reports = [];

		foreach (array_unique(array_map('intval', $reportIds)) as $reportId) {
			$report = $this->reportRepository->getReportById($reportId);

			if ($report === null) {
				continue;
			}

			// Already-actioned reports are terminal; a second decision would overwrite the
			// original moderator's name and reason.
			if (!reportStatus::fromValue($report['status'])->isPending()) {
				continue;
			}

			$reports[] = $report;
		}

		return $reports;
	}

	public function getReportById(int $reportId): ?array {
		return $this->reportRepository->getReportById($reportId);
	}

	public function getReportsPaged(int $limit, int $offset, ?int $status = null, ?int $boardUid = null): array {
		return $this->reportRepository->getReportsPaged($limit, $offset, $status, $boardUid);
	}

	public function countReports(?int $status = null, ?int $boardUid = null): int {
		return $this->reportRepository->countReports($status, $boardUid);
	}

	public function getReportsForPost(int $postUid): array {
		return $this->reportRepository->getReportsForPost($postUid);
	}

	public function getReportsByIp(string $ip, int $limit, int $offset): array {
		return $this->reportRepository->getReportsByIp($ip, $limit, $offset);
	}

	public function countReportsByIp(string $ip): int {
		return $this->reportRepository->countReportsByIp($ip);
	}

	public function getReportedPostsPaged(int $limit, int $offset): array {
		return $this->reportRepository->getReportedPostsPaged($limit, $offset);
	}

	public function countReportedPosts(): int {
		return $this->reportRepository->countReportedPosts();
	}

	public function getPostReportStats(int $postUid): array {
		return $this->reportRepository->getPostReportStats($postUid);
	}

	public function getIpReportStats(string $ip): array {
		return $this->reportRepository->getIpReportStats($ip);
	}

	public function countUnreadForAccount(int $accountId): int {
		return $this->reportRepository->countUnreadForAccount($accountId);
	}

	/**
	 * Unread pending reports filed within the last $windowMinutes minutes.
	 *
	 * @param int $windowMinutes How far back to look, in minutes.
	 */
	public function getUnreadRecentForAccount(int $accountId, int $windowMinutes, int $limit): array {
		$since = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));

		return $this->reportRepository->getUnreadForAccountSince($accountId, $since, $limit);
	}

	public function markReportsRead(array $reportIds, int $accountId): void {
		$this->reportRepository->markReportsRead($reportIds, $accountId);
	}

	public function markAllPendingRead(int $accountId): void {
		$this->reportRepository->markAllPendingRead($accountId);
	}
}
