<?php

namespace Kokonotsuba\log_in;

/**
 * Brute-force throttle for staff logins, backed by the login attempt ledger.
 *
 * Two counters gate an attempt: one per username, so a single account cannot be ground down,
 * and a looser one per IP, so an attacker cannot dodge the first by spraying usernames. Both
 * are read from the database rather than the session, which a client is free to throw away.
 */
class loginAttemptService {
	public function __construct(
		private readonly loginAttemptRepository $loginAttemptRepository,
		private readonly loginAttemptPolicy $usernamePolicy,
		private readonly loginAttemptPolicy $ipPolicy,
		private readonly int $retentionDays = 30
	) {}

	/**
	 * Seconds the caller must wait before this login may be tried, 0 when it may proceed.
	 *
	 * @param string $username Username as typed.
	 * @param string $ip       Client IP.
	 * @return int
	 */
	public function getLockoutSeconds(string $username, string $ip): int {
		$byUsername = $this->loginAttemptRepository->failureStatsForUsername(
			$username,
			$this->usernamePolicy->getWindowSeconds()
		);
		$byIp = $this->loginAttemptRepository->failureStatsForIp(
			$ip,
			$this->ipPolicy->getWindowSeconds()
		);

		return max(
			$this->usernamePolicy->remainingLockoutSeconds($byUsername['count'], $byUsername['secondsSinceLast']),
			$this->ipPolicy->remainingLockoutSeconds($byIp['count'], $byIp['secondsSinceLast'])
		);
	}

	/**
	 * Record a failed attempt.
	 *
	 * @param string   $username  Username as typed.
	 * @param int|null $accountId Account it resolved to, null when the username does not exist.
	 * @param string   $ip        Client IP.
	 * @param string   $userAgent Client user-agent.
	 * @return void
	 */
	public function recordFailure(string $username, ?int $accountId, string $ip, string $userAgent): void {
		$this->loginAttemptRepository->recordFailure($username, $accountId, $ip, $userAgent);
	}

	/**
	 * Retire the failures a successful login clears, and prune the ledger's tail.
	 * The rows stay behind unnotified so the account holder is still warned about them.
	 *
	 * @param string $username Username as typed.
	 * @param string $ip       Client IP.
	 * @return void
	 */
	public function recordSuccess(string $username, string $ip): void {
		$this->loginAttemptRepository->clearCountedForUsername($username);
		$this->loginAttemptRepository->clearCountedForIp($ip);
		$this->loginAttemptRepository->pruneOlderThan($this->retentionDays);
	}

	/**
	 * Failures an account has not been warned about yet, or null when there are none.
	 * Reading them marks them warned, so the notice is shown once per batch.
	 *
	 * @param int $accountId Account primary key.
	 * @return array{count:int, addresses:int, lastAttempt:?string}|null
	 */
	public function takePendingWarning(int $accountId): ?array {
		$pending = $this->loginAttemptRepository->pendingWarningForAccount($accountId);

		if ($pending['count'] < 1) {
			return null;
		}

		$this->loginAttemptRepository->markNotifiedForAccount($accountId);

		return $pending;
	}
}
