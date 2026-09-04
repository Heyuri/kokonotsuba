<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\ip\ipAnonymizer;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/**
 * Repository for user-filed post reports and the per-staff read receipts attached to them.
 *
 * The read receipts live in their own table but are only ever queried alongside reports
 * (an "unread" report is one with no receipt row for the viewing account), so both tables
 * are owned here rather than split across two repositories.
 */
class reportRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $reportTable,
		private readonly string $reportReadTable,
		private readonly string $accountTable,
		private readonly string $postTable,
		private readonly string $boardTable,
	) {
		parent::__construct($databaseConnection, $reportTable);
		self::validateTableNames($reportReadTable, $accountTable, $postTable, $boardTable);
	}

	/**
	 * SELECT ... FROM for a report row enriched with the data every view needs: the actioning
	 * staff member's username, the reported post's number, and the board's title.
	 */
	private function baseSelect(): string {
		return "
			SELECT
				r.*,
				a.username AS actioned_by_username,
				p.no AS post_number,
				p.host AS post_ip,
				b.board_title AS board_title
			FROM {$this->table} r
			LEFT JOIN {$this->accountTable} a ON a.id = r.actioned_by
			LEFT JOIN {$this->postTable} p ON p.post_uid = r.post_uid
			LEFT JOIN {$this->boardTable} b ON b.board_uid = r.board_uid";
	}

	/**
	 * Store a new pending report.
	 *
	 * @param int         $postUid  UID of the post being reported.
	 * @param int         $boardUid UID of the board the post lives on.
	 * @param string      $ip       IP address of the reporter.
	 * @param string|null $reason   Free-text reason, or null when the reporter left it blank.
	 * @return int The new report's id.
	 */
	public function insertReport(int $postUid, int $boardUid, string $ip, ?string $reason): int {
		$this->insert([
			'post_uid' => $postUid,
			'board_uid' => $boardUid,
			'reporter_ip' => $ip,
			'reporter_reason' => $reason,
			'status' => reportStatus::PENDING->value,
		]);

		return (int) $this->lastInsertId();
	}

	/**
	 * The anonIp module rewrites stored IPs in place as a salted hash, so a row filed before
	 * anonymization holds the raw address and the same row afterwards holds the hash. Every
	 * lookup by reporter IP has to accept both forms or it silently stops matching.
	 *
	 * Falls back to the address itself when no salt is configured: nothing has been anonymized
	 * in that case, so the second half of the IN () is simply a duplicate.
	 */
	private function ipHash(string $ip): string {
		$forms = ipAnonymizer::fromSettings()->storedForms($ip);

		return end($forms);
	}

	/**
	 * Whether this IP already has an un-actioned report open against the given post.
	 * Used to keep the queue free of the same person reporting the same post repeatedly.
	 */
	public function hasPendingReportFromIp(int $postUid, string $ip): bool {
		return $this->count(
			'post_uid = :post_uid AND reporter_ip IN (:reporter_ip, :reporter_ip_hash) AND status = :status',
			[
				':post_uid' => $postUid,
				':reporter_ip' => $ip,
				':reporter_ip_hash' => $this->ipHash($ip),
				':status' => reportStatus::PENDING->value,
			]
		) > 0;
	}

	/** Fetch one report by id, or null when it doesn't exist. */
	public function getReportById(int $reportId): ?array {
		$query = $this->baseSelect() . " WHERE r.report_id = :report_id";
		$row = $this->queryOne($query, [':report_id' => $reportId]);

		return $row === false ? null : $row;
	}

	/**
	 * Build the WHERE clause and bound parameters shared by the paged queue and its counter.
	 *
	 * @param int|null $status   Restrict to one reportStatus value, or null for any.
	 * @param int|null $boardUid Restrict to one board, or null for every board.
	 * @return array{0: string, 1: array} SQL fragment (may be empty) and its parameters.
	 */
	private function buildReportFilters(?int $status, ?int $boardUid): array {
		$conditions = [];
		$params = [];

		if ($status !== null) {
			$conditions[] = 'r.status = :status';
			$params[':status'] = $status;
		}

		if ($boardUid !== null) {
			$conditions[] = 'r.board_uid = :board_uid';
			$params[':board_uid'] = $boardUid;
		}

		$where = empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);

		return [$where, $params];
	}

	/**
	 * Fetch a page of reports newest-first, optionally filtered by status and/or board.
	 *
	 * @return array Report rows.
	 */
	public function getReportsPaged(int $limit, int $offset, ?int $status = null, ?int $boardUid = null): array {
		[$where, $params] = $this->buildReportFilters($status, $boardUid);

		$query = $this->baseSelect() . $where . " ORDER BY r.date_reported DESC, r.report_id DESC";
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAll($query, $params);
	}

	/** Total number of reports matching the same filters as getReportsPaged(). */
	public function countReports(?int $status = null, ?int $boardUid = null): int {
		[$where, $params] = $this->buildReportFilters($status, $boardUid);

		// buildReportFilters() prefixes columns with the "r" alias used by baseSelect(),
		// so alias the table here too rather than hand-rolling a second filter builder.
		$query = "SELECT COUNT(*) FROM {$this->table} r" . $where;

		return (int) $this->queryValue($query, $params);
	}

	/** Every report filed against a post, newest first. */
	public function getReportsForPost(int $postUid): array {
		$query = $this->baseSelect() . " WHERE r.post_uid = :post_uid ORDER BY r.date_reported DESC";

		return $this->queryAll($query, [':post_uid' => $postUid]);
	}

	/** Ids of the still-pending reports on a post. */
	public function getPendingReportIdsForPost(int $postUid): array {
		$query = "SELECT report_id FROM {$this->table} WHERE post_uid = :post_uid AND status = :status";
		$rows = $this->queryAll($query, [':post_uid' => $postUid, ':status' => reportStatus::PENDING->value]);

		return array_map(static fn(array $row): int => (int) $row['report_id'], $rows);
	}

	/** Ids of the still-pending reports filed from an IP. */
	public function getPendingReportIdsForIp(string $ip): array {
		$query = "
			SELECT report_id FROM {$this->table}
			WHERE reporter_ip IN (:reporter_ip, :reporter_ip_hash) AND status = :status";

		$rows = $this->queryAll($query, [
			':reporter_ip' => $ip,
			':reporter_ip_hash' => $this->ipHash($ip),
			':status' => reportStatus::PENDING->value,
		]);

		return array_map(static fn(array $row): int => (int) $row['report_id'], $rows);
	}

	/**
	 * Apply a moderator decision to a set of reports.
	 *
	 * @param array       $reportIds     Report ids to action.
	 * @param int         $status        Target reportStatus value.
	 * @param int|null    $accountId     Staff account performing the action.
	 * @param string|null $publicReason  Reason the reporter is allowed to see.
	 * @param string|null $privateReason Reason only staff can see.
	 */
	public function actionReports(
		array $reportIds,
		int $status,
		?int $accountId,
		?string $publicReason,
		?string $privateReason
	): void {
		if (empty($reportIds)) {
			return;
		}

		$reportIds = array_map('intval', $reportIds);
		$placeholders = pdoPlaceholdersForIn($reportIds);

		$query = "
			UPDATE {$this->table}
			SET status = ?, actioned_by = ?, actioned_at = NOW(), public_reason = ?, private_reason = ?
			WHERE report_id IN $placeholders";

		$params = array_merge([$status, $accountId, $publicReason, $privateReason], $reportIds);

		$this->query($query, $params);
	}

	/** A page of the reports filed from one IP, newest first. */
	public function getReportsByIp(string $ip, int $limit, int $offset): array {
		$query = $this->baseSelect()
			. " WHERE r.reporter_ip IN (:reporter_ip, :reporter_ip_hash) ORDER BY r.date_reported DESC";
		$params = [':reporter_ip' => $ip, ':reporter_ip_hash' => $this->ipHash($ip)];
		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAll($query, $params);
	}

	/** Total reports filed from one IP. */
	public function countReportsByIp(string $ip): int {
		return $this->count(
			'reporter_ip IN (:reporter_ip, :reporter_ip_hash)',
			[':reporter_ip' => $ip, ':reporter_ip_hash' => $this->ipHash($ip)]
		);
	}

	/**
	 * A page of distinct reported posts with their per-post report tallies, ordered by the
	 * most recently reported post first.
	 */
	public function getReportedPostsPaged(int $limit, int $offset): array {
		$query = "
			SELECT
				r.post_uid,
				r.board_uid,
				b.board_title AS board_title,
				p.no AS post_number,
				COUNT(*) AS report_count,
				SUM(r.status = :pending) AS pending_count,
				SUM(r.status = :approved) AS approved_count,
				SUM(r.status = :dismissed) AS dismissed_count,
				MAX(r.date_reported) AS last_reported
			FROM {$this->table} r
			LEFT JOIN {$this->postTable} p ON p.post_uid = r.post_uid
			LEFT JOIN {$this->boardTable} b ON b.board_uid = r.board_uid
			GROUP BY r.post_uid, r.board_uid, b.board_title, p.no
			ORDER BY last_reported DESC";

		$params = [
			':pending' => reportStatus::PENDING->value,
			':approved' => reportStatus::APPROVED->value,
			':dismissed' => reportStatus::DISMISSED->value,
		];

		$this->paginate($query, $params, $limit, $offset);

		return $this->queryAll($query, $params);
	}

	/** Number of distinct posts that have ever been reported. */
	public function countReportedPosts(): int {
		return (int) $this->queryValue("SELECT COUNT(DISTINCT post_uid) FROM {$this->table}");
	}

	/**
	 * Total and per-status tallies over whatever subset the WHERE fragment selects.
	 *
	 * @param string $where  SQL condition without the WHERE keyword.
	 * @param array  $params Parameters for that condition.
	 * @return array{report_count: int, pending_count: int, approved_count: int, dismissed_count: int}
	 */
	private function getStatsWhere(string $where, array $params): array {
		$query = "
			SELECT
				COUNT(*) AS report_count,
				SUM(status = :pending) AS pending_count,
				SUM(status = :approved) AS approved_count,
				SUM(status = :dismissed) AS dismissed_count
			FROM {$this->table}
			WHERE {$where}";

		$row = $this->queryOne($query, $params + [
			':pending' => reportStatus::PENDING->value,
			':approved' => reportStatus::APPROVED->value,
			':dismissed' => reportStatus::DISMISSED->value,
		]);

		return [
			'report_count' => (int) ($row['report_count'] ?? 0),
			'pending_count' => (int) ($row['pending_count'] ?? 0),
			'approved_count' => (int) ($row['approved_count'] ?? 0),
			'dismissed_count' => (int) ($row['dismissed_count'] ?? 0),
		];
	}

	/** Report tallies for a single post: total, and a breakdown by status. */
	public function getPostReportStats(int $postUid): array {
		return $this->getStatsWhere('post_uid = :post_uid', [':post_uid' => $postUid]);
	}

	/** Report tallies for everything filed from one IP. */
	public function getIpReportStats(string $ip): array {
		return $this->getStatsWhere(
			'reporter_ip IN (:reporter_ip, :reporter_ip_hash)',
			[':reporter_ip' => $ip, ':reporter_ip_hash' => $this->ipHash($ip)]
		);
	}

	// ─── Read receipts ────────────────────────────────────────────

	/**
	 * How many pending reports this account has not opened yet.
	 * Only pending reports count — an already-actioned report is not something to chase.
	 */
	public function countUnreadForAccount(int $accountId): int {
		$query = "
			SELECT COUNT(*)
			FROM {$this->table} r
			LEFT JOIN {$this->reportReadTable} rr ON rr.report_id = r.report_id AND rr.account_id = :account_id
			WHERE r.status = :status AND rr.id IS NULL";

		return (int) $this->queryValue($query, [
			':account_id' => $accountId,
			':status' => reportStatus::PENDING->value,
		]);
	}

	/**
	 * Pending reports filed since the given time that this account has not read yet.
	 * Backs the browser notifications, hence the recency window.
	 *
	 * @param string $since SQL datetime string; reports older than this are ignored.
	 */
	public function getUnreadForAccountSince(int $accountId, string $since, int $limit): array {
		$query = "
			SELECT
				r.report_id,
				r.post_uid,
				r.board_uid,
				r.reporter_reason,
				r.date_reported,
				p.no AS post_number,
				b.board_title AS board_title
			FROM {$this->table} r
			LEFT JOIN {$this->reportReadTable} rr ON rr.report_id = r.report_id AND rr.account_id = :account_id
			LEFT JOIN {$this->postTable} p ON p.post_uid = r.post_uid
			LEFT JOIN {$this->boardTable} b ON b.board_uid = r.board_uid
			WHERE r.status = :status AND rr.id IS NULL AND r.date_reported >= :since
			ORDER BY r.date_reported DESC
			LIMIT :limit";

		return $this->queryAll($query, [
			':account_id' => $accountId,
			':status' => reportStatus::PENDING->value,
			':since' => $since,
			':limit' => $limit,
		]);
	}

	/**
	 * Record that this account has seen the given reports. Re-marking an already-read report
	 * is a no-op thanks to the unique (report_id, account_id) key.
	 */
	public function markReportsRead(array $reportIds, int $accountId): void {
		if (empty($reportIds)) {
			return;
		}

		$reportIds = array_map('intval', $reportIds);

		$rowPlaceholders = implode(', ', array_fill(0, count($reportIds), '(?, ?)'));

		$params = [];
		foreach ($reportIds as $reportId) {
			$params[] = $reportId;
			$params[] = $accountId;
		}

		$query = "INSERT IGNORE INTO {$this->reportReadTable} (report_id, account_id) VALUES $rowPlaceholders";

		$this->query($query, $params);
	}

	/** Mark every currently pending report as read for this account. */
	public function markAllPendingRead(int $accountId): void {
		$query = "
			INSERT IGNORE INTO {$this->reportReadTable} (report_id, account_id)
			SELECT r.report_id, :account_id FROM {$this->table} r WHERE r.status = :status";

		$this->query($query, [
			':account_id' => $accountId,
			':status' => reportStatus::PENDING->value,
		]);
	}
}
