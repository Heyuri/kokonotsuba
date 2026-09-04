<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\Modules\anonIp\anonIpService;
use Kokonotsuba\Modules\anonIp\anonIpRepository;
use Kokonotsuba\Modules\anonIp\anonIpTarget;
use Kokonotsuba\Modules\anonIp\anonIpTargets;

/**
 * Unit tests for the IP anonymization service and its target registry.
 *
 * The repository and transaction manager are replaced by stubs (canned row counts, recorded
 * calls, pass-through transactions), so the service's timeframe resolution and orchestration
 * are tested without a database.
 */
final class AnonIpServiceTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('anonIp/anonIpTarget.php');
		requireModuleFile('anonIp/anonIpTargets.php');
		requireModuleFile('anonIp/anonIpRepository.php');
		requireModuleFile('anonIp/anonIpService.php');
	}

	/** Every logical table name anonIpTargets::build() needs. */
	private function tableNames(): array {
		return [
			'POST_TABLE' => 'posts',
			'ACTIONLOG_TABLE' => 'actionlog',
			'SOUDANE_TABLE' => 'soudane_votes',
			'REPORT_TABLE' => 'reports',
			'PRIVATE_MESSAGE_TABLE' => 'private_messages',
			'BAN_APPEAL_TABLE' => 'ban_appeals',
			'BANNER_AD_TABLE' => 'banner_ads',
			'LOGIN_ATTEMPT_TABLE' => 'login_attempts',
			'BAN_TABLE' => 'bans',
			'HOST_NOTE_TABLE' => 'host_notes',
			'DISPLAY_IP_TABLE' => 'display_ip',
		];
	}

	/**
	 * Stub repository reporting a fixed number of changed rows per target key; records the
	 * targets it was asked to anonymize and the cutoffs it was given.
	 *
	 * @param array<string, int> $changedByKey
	 */
	private function stubRepository(array $changedByKey): anonIpRepository {
		return new class($changedByKey) extends anonIpRepository {
			public array $anonymized = [];
			public array $cutoffs = [];

			public function __construct(private array $changedByKey) {}

			public function anonymize(anonIpTarget $target, ?string $cutoff): int {
				$changed = $this->changedByKey[$target->key] ?? 0;
				if ($changed > 0) {
					$this->anonymized[] = $target->key;
				}
				$this->cutoffs[] = $cutoff;

				return $changed;
			}
		};
	}

	/** Pass-through transaction manager: runs the callback with no PDO. */
	private function stubTransactionManager(): transactionManager {
		return new class extends transactionManager {
			public function __construct() {}
			public function run(callable $callback): mixed { return $callback(); }
		};
	}

	/** @param anonIpTarget[]|null $targets */
	private function makeService(anonIpRepository $repo, ?array $targets = null): anonIpService {
		return new anonIpService(
			$repo,
			$this->stubTransactionManager(),
			$targets ?? anonIpTargets::build($this->tableNames(), '2026-01-01 00:00:00')
		);
	}

	public function testUnknownTimeframeReturnsMinusOneAndTouchesNothing(): void {
		$repo = $this->stubRepository(['posts' => 5, 'reports' => 5]);
		$service = $this->makeService($repo);

		$this->assertSame(-1, $service->anonymizeByTimeframe('2fortnights'));
		$this->assertSame(-1, $service->anonymizeByTimeframe(''));
		$this->assertSame([], $repo->anonymized);
	}

	public function testKnownTimeframeSumsEveryTargetThatChangedRows(): void {
		$repo = $this->stubRepository(['posts' => 3, 'reports' => 2, 'bans' => 1]);
		$service = $this->makeService($repo);

		$this->assertSame(6, $service->anonymizeByTimeframe('1week'));
		$this->assertSame(['posts', 'reports', 'bans'], $repo->anonymized);
	}

	public function testAllRecognizedTimeframesAreAccepted(): void {
		foreach (['1year', '1month', '1week', '24hours'] as $timeframe) {
			$repo = $this->stubRepository([]);
			$result = $this->makeService($repo)->anonymizeByTimeframe($timeframe);
			$this->assertSame(0, $result, "timeframe '$timeframe' should be recognized");
		}
	}

	public function testTargetsWithNothingToChangeAreNotReported(): void {
		$repo = $this->stubRepository(['posts' => 5]);
		$service = $this->makeService($repo);

		$this->assertSame(5, $service->anonymizeByTimeframe('1month'));
		$this->assertSame(['posts'], $repo->anonymized);
		$this->assertSame(['posts' => 5], $service->getLastBreakdown());
	}

	public function testCutoffIsMysqlFormattedAndMatchesTimeframe(): void {
		$repo = $this->stubRepository([]);
		$this->makeService($repo)->anonymizeByTimeframe('24hours');

		$cutoff = $repo->cutoffs[0];
		$this->assertMatchesRegex('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cutoff);

		// Cutoff should be ~24 hours in the past (generous 2-minute tolerance).
		$delta = abs(strtotime($cutoff) - (time() - 86400));
		$this->assertLessThan(120, $delta);
	}

	public function testAnonymizeAllPassesNoCutoffAndRunsEveryTarget(): void {
		$targets = anonIpTargets::build($this->tableNames(), '2026-01-01 00:00:00');
		$repo = $this->stubRepository(['posts' => 10, 'actionLog' => 20, 'displayIp' => 30]);

		$this->assertSame(60, $this->makeService($repo, $targets)->anonymizeAll());
		$this->assertSame(['posts', 'actionLog', 'displayIp'], $repo->anonymized);
		$this->assertCount(count($targets), $repo->cutoffs);
		$this->assertSame([null], array_values(array_unique($repo->cutoffs)));
	}

	public function testAnonymizeAllWithNothingToDoIsANoOp(): void {
		$repo = $this->stubRepository([]);
		$service = $this->makeService($repo);

		$this->assertSame(0, $service->anonymizeAll());
		$this->assertSame([], $repo->anonymized);
		$this->assertSame([], $service->getLastBreakdown());
	}

	public function testTargetsThatCannotBeAgedAreSkippedByATimeframeRun(): void {
		$undatable = new anonIpTarget('undatable', 'some_table', 'ip_address');
		$repo = $this->stubRepository(['undatable' => 7]);

		$this->assertSame(0, $this->makeService($repo, [$undatable])->anonymizeByTimeframe('1week'));
		$this->assertSame([], $repo->anonymized);

		// A full run still reaches it.
		$repo = $this->stubRepository(['undatable' => 7]);
		$this->assertSame(7, $this->makeService($repo, [$undatable])->anonymizeAll());
	}

	public function testEveryIpBearingTableIsRegisteredExactlyOnce(): void {
		$targets = anonIpTargets::build($this->tableNames(), '2026-01-01 00:00:00');
		$keys = array_map(fn(anonIpTarget $t) => $t->key, $targets);

		$this->assertSame($keys, array_values(array_unique($keys)));
		$this->assertSame(
			[
				'posts', 'postTokens', 'actionLog', 'soudane', 'reports', 'privateMessages',
				'banAppeals', 'bannerAds', 'loginAttempts', 'bans', 'hostNotes', 'displayIp',
			],
			$keys
		);
	}

	public function testLapsedBansOnlyAndDisplayIpIsClearedRatherThanHashed(): void {
		$byKey = [];
		foreach (anonIpTargets::build($this->tableNames(), '2026-01-01 00:00:00') as $target) {
			$byKey[$target->key] = $target;
		}

		// A ban's pattern is what enforces it, so an enforceable ban must never be touched.
		$this->assertStringContains('is_wildcard = 0', $byKey['bans']->guardSql);
		$this->assertStringContains('revoked_at IS NOT NULL', $byKey['bans']->guardSql);
		$this->assertSame('2026-01-01 00:00:00', $byKey['bans']->guardParams[':ban_now']);

		// The public display fragment is blanked, not replaced by a hash on the page.
		$this->assertSame(anonIpTarget::MODE_CLEAR, $byKey['displayIp']->mode);
		$this->assertSame('', $byKey['displayIp']->clearTo);
		$this->assertSame(anonIpTarget::MODE_HASH, $byKey['posts']->mode);

		// A post's browser token hash is discarded outright, back to the NULL a post written
		// before the column existed holds.
		$this->assertSame(anonIpTarget::MODE_CLEAR, $byKey['postTokens']->mode);
		$this->assertNull($byKey['postTokens']->clearTo);
		$this->assertSame('visitor_token_hash', $byKey['postTokens']->ipColumn);
	}

	public function testTargetRejectsBadIdentifiersAndModes(): void {
		$this->assertThrows(
			fn() => new anonIpTarget('bad', 'posts; DROP TABLE posts', 'host'),
			\InvalidArgumentException::class
		);

		$this->assertThrows(
			fn() => new anonIpTarget('bad', 'posts', 'host = 1 OR 1'),
			\InvalidArgumentException::class
		);

		$this->assertThrows(
			fn() => new anonIpTarget('bad', 'posts', 'host', 'shred'),
			\InvalidArgumentException::class
		);
	}
}
