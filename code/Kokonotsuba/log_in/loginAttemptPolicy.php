<?php

namespace Kokonotsuba\log_in;

/**
 * Decides when a login is throttled, from nothing but a failure count and how long ago the last
 * failure was. Pure by design: the repository does the counting, this does the arithmetic, so
 * the backoff curve is testable without a database.
 *
 * Failures older than the window stop counting, so a lockout unwinds on its own once the
 * hammering stops.
 */
class loginAttemptPolicy {
	/**
	 * @param int $maxAttempts    Failures inside the window before locking out.
	 * @param int $windowSeconds  How far back failures are counted.
	 * @param int $baseSeconds    Lockout applied at the first threshold breach.
	 * @param int $maxSeconds     Ceiling the doubling backoff stops at.
	 */
	public function __construct(
		private readonly int $maxAttempts,
		private readonly int $windowSeconds,
		private readonly int $baseSeconds,
		private readonly int $maxSeconds
	) {}

	/** How far back the caller should count failures. */
	public function getWindowSeconds(): int {
		return max(1, $this->windowSeconds);
	}

	/** Failures allowed inside the window before a lockout starts. */
	public function getMaxAttempts(): int {
		return max(1, $this->maxAttempts);
	}

	/**
	 * Lockout length earned by a given number of failures: doubles with every failure past the
	 * threshold, capped at $maxSeconds. Zero below the threshold.
	 */
	public function lockoutSecondsFor(int $failureCount): int {
		if ($failureCount < $this->getMaxAttempts()) {
			return 0;
		}

		$base = max(1, $this->baseSeconds);
		$ceiling = max($base, $this->maxSeconds);

		// Cap the exponent before shifting: 2 ** 60 overflows to a float and loses the cap.
		$excess = min(30, $failureCount - $this->getMaxAttempts());

		return (int)min($ceiling, $base * (2 ** $excess));
	}

	/**
	 * Seconds still to wait, or 0 when the caller may attempt a login.
	 *
	 * @param int      $failureCount      Counted failures inside the window.
	 * @param int|null $secondsSinceLast  Age of the newest counted failure, null when there is none.
	 */
	public function remainingLockoutSeconds(int $failureCount, ?int $secondsSinceLast): int {
		$lockout = $this->lockoutSecondsFor($failureCount);

		if ($lockout === 0 || $secondsSinceLast === null) {
			return 0;
		}

		return max(0, $lockout - max(0, $secondsSinceLast));
	}

	/** Whether a login attempt should be refused outright. */
	public function isLockedOut(int $failureCount, ?int $secondsSinceLast): bool {
		return $this->remainingLockoutSeconds($failureCount, $secondsSinceLast) > 0;
	}
}
