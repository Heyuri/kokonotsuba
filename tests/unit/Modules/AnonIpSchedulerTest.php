<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\anonIp\anonIpRunRepository;
use Kokonotsuba\Modules\anonIp\anonIpScheduler;

/**
 * Unit tests for the anonymizer's schedule.
 *
 * The run ledger is stubbed, so what is tested here is the decision - whether a run is due, that
 * it covers every record, and that a failure never escapes onto the request - not the SQL that
 * claims it, which needs a live database (see tests/integration/anonIp.php).
 */
final class AnonIpSchedulerTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('anonIp/anonIpRunRepository.php');
		requireModuleFile('anonIp/anonIpScheduler.php');
	}

	/**
	 * Stub ledger recording the claims it was asked for.
	 *
	 * @param int|null $claimId Id to hand back, or null to refuse the claim.
	 */
	private function stubLedger(?int $claimId, bool $throws = false): anonIpRunRepository {
		return new class($claimId, $throws) extends anonIpRunRepository {
			public array $claims = [];
			public array $discarded = [];

			public function __construct(private ?int $claimId, private bool $throws) {}

			public function claimScheduledRun(string $notBefore, ?int $olderThanDays): ?int {
				if ($this->throws) {
					throw new \RuntimeException('the database went away');
				}

				$this->claims[] = ['notBefore' => $notBefore, 'olderThanDays' => $olderThanDays];

				return $this->claimId;
			}

			public function discardRun(int $runId): void {
				$this->discarded[] = $runId;
			}
		};
	}

	public function testAnIntervalOfZeroDisablesTheScheduleEntirely(): void {
		// Nothing should reach the ledger: tick() is on the request path.
		$ledger = $this->stubLedger(1);

		$this->assertFalse((new anonIpScheduler($ledger, 0))->tick());
		$this->assertFalse((new anonIpScheduler($ledger, -7))->tick());
		$this->assertSame([], $ledger->claims);
	}

	public function testTheCutoffIsTheIntervalBackFromNow(): void {
		$ledger = $this->stubLedger(null);
		(new anonIpScheduler($ledger, 7))->tick();

		$notBefore = $ledger->claims[0]['notBefore'];
		$this->assertMatchesRegex('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $notBefore);

		$delta = abs(strtotime($notBefore) - (time() - 7 * 86400));
		$this->assertLessThan(120, $delta);
	}

	/**
	 * A standing schedule has no age window: narrowing a run is a per-run choice made from the
	 * page. Anything else here would leave the oldest addresses hashed and newer ones not.
	 */
	public function testAScheduledRunCoversEveryRecord(): void {
		$ledger = $this->stubLedger(null);
		(new anonIpScheduler($ledger, 7))->tick();

		$this->assertNull($ledger->claims[0]['olderThanDays'], 'a scheduled run should not be aged');
	}

	public function testARefusedClaimDispatchesNothing(): void {
		// Another request got there first, or the last run is still inside the interval.
		$ledger = $this->stubLedger(null);

		$this->assertFalse((new anonIpScheduler($ledger, 7))->tick());
		$this->assertCount(1, $ledger->claims);
	}

	public function testAFailureIsSwallowedSoTheRequestCarriesOn(): void {
		// Nothing the visitor asked for depends on the anonymizer running.
		$ledger = $this->stubLedger(1, throws: true);

		$this->assertFalse((new anonIpScheduler($ledger, 7))->tick());
	}

	public function testAClaimWhoseDispatchFailsIsGivenBack(): void {
		// BackgroundTaskDispatcher is unconfigured here, so the dispatch throws — which is
		// exactly the case the claim has to survive, or the schedule stalls for a full interval.
		$ledger = $this->stubLedger(42);

		$this->assertFalse((new anonIpScheduler($ledger, 7))->tick());
		$this->assertSame([42], $ledger->discarded);
	}
}
