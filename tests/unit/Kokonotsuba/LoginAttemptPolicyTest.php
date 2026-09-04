<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\log_in\loginAttemptPolicy;

/**
 * Unit tests for the staff login brute-force backoff.
 *
 * The counting itself is SQL, so what is left to pin down here is the curve: when a lockout
 * starts, how fast it grows, and that it neither overflows nor outlives its ceiling.
 */
final class LoginAttemptPolicyTest extends TestCase {

	/** 5 failures in 15 minutes locks out for 30s, doubling to at most an hour. */
	private function policy(): loginAttemptPolicy {
		return new loginAttemptPolicy(5, 900, 30, 3600);
	}

	public function testBelowThresholdIsNeverLockedOut(): void {
		$policy = $this->policy();

		$this->assertSame(0, $policy->lockoutSecondsFor(0));
		$this->assertSame(0, $policy->lockoutSecondsFor(4));
		$this->assertFalse($policy->isLockedOut(4, 0));
	}

	public function testThresholdEarnsTheBaseLockout(): void {
		$this->assertSame(30, $this->policy()->lockoutSecondsFor(5));
	}

	public function testLockoutDoublesWithEachFurtherFailure(): void {
		$policy = $this->policy();

		$this->assertSame(60, $policy->lockoutSecondsFor(6));
		$this->assertSame(120, $policy->lockoutSecondsFor(7));
		$this->assertSame(240, $policy->lockoutSecondsFor(8));
	}

	public function testLockoutIsCappedAtTheCeiling(): void {
		$policy = $this->policy();

		$this->assertSame(3600, $policy->lockoutSecondsFor(20));
		// A sustained attack must not overflow the shift into a float and lose the cap.
		$this->assertSame(3600, $policy->lockoutSecondsFor(100000));
	}

	public function testRemainingCountsDownFromTheLastFailure(): void {
		$policy = $this->policy();

		$this->assertSame(30, $policy->remainingLockoutSeconds(5, 0));
		$this->assertSame(10, $policy->remainingLockoutSeconds(5, 20));
		$this->assertSame(0, $policy->remainingLockoutSeconds(5, 30));
		$this->assertSame(0, $policy->remainingLockoutSeconds(5, 400));
	}

	public function testNoFailuresInWindowMeansNoLockout(): void {
		// The window has aged every failure out, so the count arrives at 0 with no last failure.
		$this->assertSame(0, $this->policy()->remainingLockoutSeconds(0, null));
		$this->assertFalse($this->policy()->isLockedOut(0, null));
	}

	public function testIsLockedOutTracksTheRemainder(): void {
		$policy = $this->policy();

		$this->assertTrue($policy->isLockedOut(5, 29));
		$this->assertFalse($policy->isLockedOut(5, 31));
	}

	/** A clock skew that reports the newest failure as being in the future must not extend a lockout. */
	public function testNegativeAgeIsTreatedAsJustNow(): void {
		$this->assertSame(30, $this->policy()->remainingLockoutSeconds(5, -500));
	}

	public function testDegenerateSettingsAreClampedRatherThanDisablingTheThrottle(): void {
		$policy = new loginAttemptPolicy(0, 0, 0, 0);

		$this->assertSame(1, $policy->getMaxAttempts());
		$this->assertSame(1, $policy->getWindowSeconds());
		$this->assertSame(1, $policy->lockoutSecondsFor(1));
	}

	public function testWindowAndThresholdAreReportedForTheQuery(): void {
		$policy = $this->policy();

		$this->assertSame(900, $policy->getWindowSeconds());
		$this->assertSame(5, $policy->getMaxAttempts());
	}
}
