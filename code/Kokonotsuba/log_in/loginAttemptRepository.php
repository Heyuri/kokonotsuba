<?php

namespace Kokonotsuba\log_in;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/**
 * Ledger of failed staff logins.
 *
 * Two flags decide what a row is still good for:
 *  - `counted`  the failure still counts toward a lockout. Cleared by a successful login, so a
 *               staff member who eventually gets in is not held to their own typos.
 *  - `notified` the account holder has been shown the failure in the admin warning. Survives
 *               `counted` being cleared, which is the whole point: they get told about the
 *               attempts made against them even though they just logged in fine.
 *
 * Every window is measured in SQL against NOW(), never against PHP's clock, so a database in a
 * different time zone to the web server cannot skew a lockout.
 */
class loginAttemptRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $loginAttemptTable
	) {
		parent::__construct($databaseConnection, $loginAttemptTable);
	}

	/**
	 * Record one failed login.
	 *
	 * @param string   $username  Username exactly as typed.
	 * @param int|null $accountId Account it resolved to, null when no such account exists.
	 * @param string   $ip        Client IP.
	 * @param string   $userAgent Client user-agent, truncated to the column width.
	 * @return void
	 */
	public function recordFailure(string $username, ?int $accountId, string $ip, string $userAgent): void {
		$this->insert([
			'username' => mb_substr($username, 0, 255),
			'username_key' => $this->normalizeUsername($username),
			'account_id' => $accountId,
			'ip' => mb_substr($ip, 0, 45),
			'user_agent' => $userAgent === '' ? null : mb_substr($userAgent, 0, 255),
		]);
	}

	/**
	 * Count still-counting failures against a username inside the window.
	 *
	 * @param string $username      Username as typed; matched case-insensitively.
	 * @param int    $windowSeconds How far back to look.
	 * @return array{count:int, secondsSinceLast:?int}
	 */
	public function failureStatsForUsername(string $username, int $windowSeconds): array {
		return $this->failureStats('username_key', $this->normalizeUsername($username), $windowSeconds);
	}

	/**
	 * Count still-counting failures from an IP inside the window, across every username it tried.
	 *
	 * @param string $ip            Client IP.
	 * @param int    $windowSeconds How far back to look.
	 * @return array{count:int, secondsSinceLast:?int}
	 */
	public function failureStatsForIp(string $ip, int $windowSeconds): array {
		return $this->failureStats('ip', $ip, $windowSeconds);
	}

	/**
	 * Stop a username's failures counting toward a lockout, after it authenticates successfully.
	 *
	 * @param string $username Username as typed.
	 * @return void
	 */
	public function clearCountedForUsername(string $username): void {
		$this->updateWhere(['counted' => 0], 'username_key', $this->normalizeUsername($username));
	}

	/**
	 * Stop an IP's failures counting toward a lockout, after someone authenticates from it.
	 *
	 * @param string $ip Client IP.
	 * @return void
	 */
	public function clearCountedForIp(string $ip): void {
		$this->updateWhere(['counted' => 0], 'ip', $ip);
	}

	/**
	 * Summarize the failures an account has not been warned about yet.
	 *
	 * @param int $accountId Account primary key.
	 * @return array{count:int, addresses:int, lastAttempt:?string}
	 */
	public function pendingWarningForAccount(int $accountId): array {
		$row = $this->queryOne(
			"SELECT COUNT(*) AS failures, COUNT(DISTINCT ip) AS addresses, MAX(attempted_at) AS last_attempt
			 FROM {$this->table}
			 WHERE account_id = :account_id AND notified = 0",
			[':account_id' => $accountId]
		);

		return [
			'count' => (int)($row['failures'] ?? 0),
			'addresses' => (int)($row['addresses'] ?? 0),
			'lastAttempt' => $row['last_attempt'] ?? null,
		];
	}

	/**
	 * Mark an account's outstanding failures as warned about, so the notice shows once.
	 *
	 * @param int $accountId Account primary key.
	 * @return void
	 */
	public function markNotifiedForAccount(int $accountId): void {
		$this->query(
			"UPDATE {$this->table} SET notified = 1 WHERE account_id = :account_id AND notified = 0",
			[':account_id' => $accountId]
		);
	}

	/**
	 * Drop rows older than the retention period, keeping the ledger from growing without bound.
	 *
	 * @param int $retentionDays Age past which a row is discarded.
	 * @return void
	 */
	public function pruneOlderThan(int $retentionDays): void {
		$days = max(1, $retentionDays);

		// Interval literals cannot be bound as parameters; $days is an int, never request data.
		$this->query("DELETE FROM {$this->table} WHERE attempted_at < (NOW() - INTERVAL {$days} DAY)");
	}

	/**
	 * @param string $column        Indexed column to group the failures by.
	 * @param string $value         Value to match.
	 * @param int    $windowSeconds How far back to look.
	 * @return array{count:int, secondsSinceLast:?int}
	 */
	private function failureStats(string $column, string $value, int $windowSeconds): array {
		$seconds = max(1, $windowSeconds);

		// As above: the interval bound is an int from config, and the column name is a literal.
		$row = $this->queryOne(
			"SELECT COUNT(*) AS failures, TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS seconds_since_last
			 FROM {$this->table}
			 WHERE {$column} = :value AND counted = 1 AND attempted_at > (NOW() - INTERVAL {$seconds} SECOND)",
			[':value' => $value]
		);

		return [
			'count' => (int)($row['failures'] ?? 0),
			'secondsSinceLast' => isset($row['seconds_since_last']) && $row['seconds_since_last'] !== null
				? (int)$row['seconds_since_last']
				: null,
		];
	}

	/** Fold a username to its lookup key so case variants share one lockout counter. */
	private function normalizeUsername(string $username): string {
		return mb_substr(mb_strtolower(trim($username)), 0, 255);
	}
}
