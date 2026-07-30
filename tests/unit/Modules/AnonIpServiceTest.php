<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\Modules\anonIp\anonIpService;
use Kokonotsuba\Modules\anonIp\anonIpRepository;

/**
 * Unit tests for the IP anonymization service.
 *
 * The repository and transaction manager are replaced by stubs (canned counts,
 * recorded calls, pass-through transactions), so the service's timeframe
 * resolution and orchestration logic is tested without a database.
 */
final class AnonIpServiceTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('anonIp/anonIpRepository.php');
		requireModuleFile('anonIp/anonIpService.php');
	}

	/** Stub repository with configurable counts; records which anonymize calls ran. */
	private function stubRepository(int $posts, int $actionLog, int $soudane): anonIpRepository {
		return new class($posts, $actionLog, $soudane) extends anonIpRepository {
			public array $anonymized = [];
			public array $cutoffs = [];
			public function __construct(
				private int $posts,
				private int $actionLog,
				private int $soudane
			) {}
			public function countToAnonymize(string $cutoff): int {
				$this->cutoffs[] = $cutoff;
				return $this->posts;
			}
			public function countActionLogToAnonymize(string $cutoff): int { return $this->actionLog; }
			public function countSoudaneToAnonymize(string $cutoff): int { return $this->soudane; }
			public function countAllToAnonymize(): int { return $this->posts; }
			public function countAllActionLogToAnonymize(): int { return $this->actionLog; }
			public function countAllSoudaneToAnonymize(): int { return $this->soudane; }
			public function anonymizeBefore(string $cutoff): void { $this->anonymized[] = 'posts'; }
			public function anonymizeActionLogBefore(string $cutoff): void { $this->anonymized[] = 'actionLog'; }
			public function anonymizeSoudaneBefore(string $cutoff): void { $this->anonymized[] = 'soudane'; }
			public function anonymizeAll(): void { $this->anonymized[] = 'posts'; }
			public function anonymizeAllActionLog(): void { $this->anonymized[] = 'actionLog'; }
			public function anonymizeAllSoudane(): void { $this->anonymized[] = 'soudane'; }
		};
	}

	/** Pass-through transaction manager: runs the callback with no PDO. */
	private function stubTransactionManager(): transactionManager {
		return new class extends transactionManager {
			public function __construct() {}
			public function run(callable $callback): mixed { return $callback(); }
		};
	}

	private function makeService(anonIpRepository $repo): anonIpService {
		return new anonIpService($repo, $this->stubTransactionManager());
	}

	public function testUnknownTimeframeReturnsMinusOneAndTouchesNothing(): void {
		$repo = $this->stubRepository(5, 5, 5);
		$service = $this->makeService($repo);

		$this->assertSame(-1, $service->anonymizeByTimeframe('2fortnights'));
		$this->assertSame(-1, $service->anonymizeByTimeframe(''));
		$this->assertSame([], $repo->anonymized);
	}

	public function testKnownTimeframeReturnsSumAcrossAllThreeTables(): void {
		$repo = $this->stubRepository(3, 2, 1);
		$service = $this->makeService($repo);

		$this->assertSame(6, $service->anonymizeByTimeframe('1week'));
		$this->assertSame(['posts', 'actionLog', 'soudane'], $repo->anonymized);
	}

	public function testAllRecognizedTimeframesAreAccepted(): void {
		foreach (['1year', '1month', '1week', '24hours'] as $timeframe) {
			$repo = $this->stubRepository(0, 0, 0);
			$result = $this->makeService($repo)->anonymizeByTimeframe($timeframe);
			$this->assertSame(0, $result, "timeframe '$timeframe' should be recognized");
		}
	}

	public function testTablesWithZeroRowsAreNotAnonymized(): void {
		// Only the posts table has rows to anonymize; the other two must be skipped.
		$repo = $this->stubRepository(5, 0, 0);
		$service = $this->makeService($repo);

		$this->assertSame(5, $service->anonymizeByTimeframe('1month'));
		$this->assertSame(['posts'], $repo->anonymized);
	}

	public function testCutoffIsMysqlFormattedAndMatchesTimeframe(): void {
		$repo = $this->stubRepository(0, 0, 0);
		$this->makeService($repo)->anonymizeByTimeframe('24hours');

		$this->assertCount(1, $repo->cutoffs);
		$cutoff = $repo->cutoffs[0];
		$this->assertMatchesRegex('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoff);

		// Cutoff should be ~24 hours in the past (generous 2-minute tolerance).
		$delta = abs(strtotime($cutoff) - (time() - 86400));
		$this->assertLessThan(120, $delta);
	}

	public function testAnonymizeAllReturnsSumAndRunsEveryTable(): void {
		$repo = $this->stubRepository(10, 20, 30);
		$service = $this->makeService($repo);

		$this->assertSame(60, $service->anonymizeAll());
		$this->assertSame(['posts', 'actionLog', 'soudane'], $repo->anonymized);
	}

	public function testAnonymizeAllWithNothingToDoIsANoOp(): void {
		$repo = $this->stubRepository(0, 0, 0);
		$service = $this->makeService($repo);

		$this->assertSame(0, $service->anonymizeAll());
		$this->assertSame([], $repo->anonymized);
	}
}
