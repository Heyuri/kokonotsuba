<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\postStats\postStatsBuildQueue;
use Kokonotsuba\Modules\postStats\postStatsRepository;
use Kokonotsuba\Modules\postStats\postStatsService;

/**
 * Unit tests for the post statistics service.
 *
 * The repository is replaced by a stub serving canned per-day maxima, so the parts that matter —
 * turning a post-number sequence into per-day counts, and extending yesterday's cache instead of
 * rebuilding it — are tested without a database.
 */
final class PostStatsServiceTest extends TestCase {
	private string $cacheDirectory = '';

	protected function setUp(): void {
		requireModuleFile('postStats/postStatsRepository.php');
		requireModuleFile('postStats/postStatsService.php');

		$this->cacheDirectory = sys_get_temp_dir() . '/kokoPostStatsTest' . getmypid() . '/';
	}

	protected function tearDown(): void {
		foreach (glob($this->cacheDirectory . '*') ?: [] as $file) {
			unlink($file);
		}
		if (is_dir($this->cacheDirectory)) {
			rmdir($this->cacheDirectory);
		}
	}

	/**
	 * Stub repository.
	 *
	 * $dailyRows is keyed by board uid, each a list of ['day', 'min_no', 'max_no'] — the surviving
	 * low and high post number of that day. The differencing the real repository asks the server
	 * to do with LAG is reproduced here, so the service is tested against the row shape it
	 * actually receives.
	 */
	private function repository(string $today, array $dailyRows, array $currentNumbers): postStatsRepository {
		return new class($today, $dailyRows, $currentNumbers) extends postStatsRepository {
			public int $dailyQueryCount = 0;

			public function __construct(
				private string $today,
				private array $dailyRows,
				private array $currentNumbers,
			) {}

			public function getSnapshot(array $boardUids): array {
				return [
					'today' => $this->today,
					'numbers' => array_intersect_key($this->currentNumbers, array_flip($boardUids)),
				];
			}

			public function getDailySeries(int $boardUid, string $fromDay = '', ?int $boundary = null): array {
				$this->dailyQueryCount++;

				return $this->difference($this->window($boardUid, $fromDay), $boundary);
			}

			public function getDailySeriesForBoards(array $boardUids, string $fromDay = ''): array {
				$this->dailyQueryCount++;
				$rows = [];

				// ORDER BY board_uid, day — grouped by board, oldest first within each.
				foreach ($boardUids as $uid) {
					foreach ($this->difference($this->window($uid, $fromDay), null) as $row) {
						$rows[] = ['board_uid' => $uid] + $row;
					}
				}

				return $rows;
			}

			private function window(int $boardUid, string $fromDay): array {
				return array_values(array_filter(
					$this->dailyRows[$boardUid] ?? [],
					fn($row) => ($fromDay === '' || $row['day'] >= $fromDay) && $row['day'] < $this->today
				));
			}

			/** What COALESCE(LAG(max_no) OVER (...), :boundary, 0) does server-side. */
			private function difference(array $rows, ?int $boundary): array {
				$previous = null;
				$out = [];

				foreach ($rows as $row) {
					$base = $previous ?? $boundary ?? 0;
					$out[] = $row + ['made' => max(0, $row['max_no'] - $base)];
					$previous = $row['max_no'];
				}

				return $out;
			}
		};
	}

	/** A board whose numbering starts at 1, with a gap left by deleted posts on the second day. */
	private function sampleRepository(string $today = '2026-08-06'): postStatsRepository {
		return $this->repository(
			$today,
			[7 => [
				['day' => '2026-08-03', 'min_no' => 1, 'max_no' => 10],
				['day' => '2026-08-04', 'min_no' => 11, 'max_no' => 30],
				['day' => '2026-08-05', 'min_no' => 31, 'max_no' => 45],
			]],
			[7 => 50]
		);
	}

	public function testDailyCountsAreDifferencesInThePostNumberSequence(): void {
		$service = new postStatsService($this->sampleRepository(), $this->cacheDirectory);

		$stats = $service->getBoardStats(7);

		$this->assertSame(10, $stats['days']['2026-08-03']);
		$this->assertSame(20, $stats['days']['2026-08-04']);
		$this->assertSame(15, $stats['days']['2026-08-05']);
	}

	public function testDeletedPostsStillCountTowardsTheDayTheyWereMade(): void {
		// Day two has nothing left below no. 21 — 11 to 20 were deleted as spam — so counting the
		// surviving rows would say 10. The gap in the sequence says 20, which is what was made.
		$repository = $this->repository(
			'2026-08-06',
			[7 => [
				['day' => '2026-08-04', 'min_no' => 1, 'max_no' => 10],
				['day' => '2026-08-05', 'min_no' => 21, 'max_no' => 30],
			]],
			[7 => 30]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame(20, $stats['days']['2026-08-05']);
		$this->assertSame(30, $stats['total']);
	}

	public function testTodayComesFromTheCurrentPostNumberRatherThanSurvivingRows(): void {
		$stats = (new postStatsService($this->sampleRepository(), $this->cacheDirectory))->getBoardStats(7);

		// End of yesterday was 45, the counter now reads 50.
		$this->assertSame(5, $stats['todayCount']);
		$this->assertSame(5, $stats['days']['2026-08-06']);
	}

	public function testTotalSpansFirstPostToLatestNumber(): void {
		$stats = (new postStatsService($this->sampleRepository(), $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame(50, $stats['total']);
		$this->assertSame(1, $stats['firstNo']);
		$this->assertSame(50, $stats['lastNo']);
		$this->assertSame('2026-08-03', $stats['firstDay']);
	}

	public function testNumbersWhosePostsWerePurgedStillCount(): void {
		// Nothing below no. 1000 survives on this board — an old sweep took the lot. The sequence
		// still ran from 1, so 1010 posts were made, and counting rows would say 11.
		$repository = $this->repository(
			'2026-08-06',
			[7 => [['day' => '2026-08-05', 'min_no' => 1000, 'max_no' => 1010]]],
			[7 => 1010]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame(1010, $stats['days']['2026-08-05']);
		$this->assertSame(1010, $stats['total']);
	}

	public function testTotalComesFromTheCounterRatherThanTheSurvivingRows(): void {
		// A board swept down to two posts still reports every number it ever handed out.
		$repository = $this->repository(
			'2026-08-06',
			[7 => [['day' => '2026-08-05', 'min_no' => 8000, 'max_no' => 9000]]],
			[7 => 9500]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame(9500, $stats['total']);
		$this->assertSame(9000, $stats['days']['2026-08-05']);
		$this->assertSame(500, $stats['todayCount']);
	}

	public function testSecondViewOnTheSameDayReusesTheCachedDays(): void {
		$repository = $this->sampleRepository();
		$service = new postStatsService($repository, $this->cacheDirectory);

		$service->getBoardStats(7);
		$queriesAfterBuild = $repository->dailyQueryCount;
		$service->getBoardStats(7);

		$this->assertSame($queriesAfterBuild, $repository->dailyQueryCount);
	}

	public function testNextDayExtendsTheCacheAndKeepsTheOlderDays(): void {
		$service = new postStatsService($this->sampleRepository('2026-08-06'), $this->cacheDirectory);
		$service->getBoardStats(7);

		// The day turns over; 2026-08-06 completes on 50 and the counter moves on.
		$laterRepository = $this->repository(
			'2026-08-07',
			[7 => [
				['day' => '2026-08-03', 'min_no' => 1, 'max_no' => 10],
				['day' => '2026-08-04', 'min_no' => 11, 'max_no' => 30],
				['day' => '2026-08-05', 'min_no' => 31, 'max_no' => 45],
				['day' => '2026-08-06', 'min_no' => 46, 'max_no' => 50],
			]],
			[7 => 62]
		);

		$stats = (new postStatsService($laterRepository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame(10, $stats['days']['2026-08-03']);
		$this->assertSame(5, $stats['days']['2026-08-06']);
		$this->assertSame(12, $stats['todayCount']);
		// Only the days after the cached one were queried again.
		$this->assertSame(1, $laterRepository->dailyQueryCount);
	}

	public function testBoardWithNoPostsReportsZeroesRatherThanFailing(): void {
		$repository = $this->repository('2026-08-06', [], [9 => 0]);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(9);

		$this->assertSame([], $stats['days']);
		$this->assertSame(0, $stats['total']);
		$this->assertSame('', $stats['firstDay']);
	}

	public function testSiteTotalsSumTheBoardsDayByDay(): void {
		$repository = $this->repository(
			'2026-08-06',
			[
				7 => [['day' => '2026-08-05', 'min_no' => 1, 'max_no' => 45]],
				8 => [['day' => '2026-08-05', 'min_no' => 100, 'max_no' => 200]],
			],
			[7 => 50, 8 => 210]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getSiteStats([7, 8]);

		// Each board counts from the start of its own numbering, not from its first surviving post.
		$this->assertSame(45 + 200, $stats['days']['2026-08-05']);
		$this->assertSame(15, $stats['todayCount']);
		$this->assertSame(50 + 210, $stats['total']);
		$this->assertSame('2026-08-05', $stats['firstDay']);
		$this->assertSame(210, $stats['boards'][8]['total']);
	}

	/** Records what it was asked to build, and answers whether it took the job. */
	private function queue(bool $accepts): postStatsBuildQueue {
		return new class($accepts) implements postStatsBuildQueue {
			public array $requests = [];
			public function __construct(private bool $accepts) {}
			public function request(string $scope, array $args): bool {
				$this->requests[] = $scope;
				return $this->accepts;
			}
		};
	}

	public function testFirstBuildIsHandedToTheQueueRatherThanRunInThePageView(): void {
		$repository = $this->sampleRepository();
		$queue = $this->queue(true);

		$stats = (new postStatsService($repository, $this->cacheDirectory, $queue))->getBoardStats(7);

		$this->assertSame(['board-7'], $queue->requests);
		$this->assertTrue($stats['generating']);
		$this->assertSame([], $stats['days']);
		// The expensive history query never ran.
		$this->assertSame(0, $repository->dailyQueryCount);
	}

	public function testBuildRunsInlineWhenTheQueueCannotTakeIt(): void {
		$repository = $this->sampleRepository();
		$queue = $this->queue(false);

		$stats = (new postStatsService($repository, $this->cacheDirectory, $queue))->getBoardStats(7);

		$this->assertFalse($stats['generating']);
		$this->assertSame(10, $stats['days']['2026-08-03']);
		$this->assertSame(1, $repository->dailyQueryCount);
	}

	public function testExtendingAnExistingCacheStaysInlineInsteadOfBeingDeferred(): void {
		// Build it once so a cache exists.
		(new postStatsService($this->sampleRepository('2026-08-06'), $this->cacheDirectory))->getBoardStats(7);

		$laterRepository = $this->repository(
			'2026-08-07',
			[7 => [
				['day' => '2026-08-03', 'min_no' => 1, 'max_no' => 10],
				['day' => '2026-08-04', 'min_no' => 11, 'max_no' => 30],
				['day' => '2026-08-05', 'min_no' => 31, 'max_no' => 45],
				['day' => '2026-08-06', 'min_no' => 46, 'max_no' => 50],
			]],
			[7 => 62]
		);
		$queue = $this->queue(true);

		$stats = (new postStatsService($laterRepository, $this->cacheDirectory, $queue))->getBoardStats(7);

		$this->assertSame([], $queue->requests);
		$this->assertFalse($stats['generating']);
		$this->assertSame(5, $stats['days']['2026-08-06']);
	}

	public function testTheSiteBuildIsDeferredSeparatelyFromTheBoardOne(): void {
		$repository = $this->sampleRepository();
		$queue = $this->queue(true);

		$stats = (new postStatsService($repository, $this->cacheDirectory, $queue))->getSiteStats([7]);

		$this->assertSame(['site'], $queue->requests);
		$this->assertTrue($stats['generating']);
		$this->assertSame([], $stats['boards']);
	}

	public function testTheBackgroundRebuildProducesTheSameCacheAsAnInlineBuild(): void {
		$inline = (new postStatsService($this->sampleRepository(), $this->cacheDirectory))->getBoardStats(7);

		foreach (glob($this->cacheDirectory . '*') ?: [] as $file) {
			unlink($file);
		}

		// What the background task does, then what the next page view reads back.
		$service = new postStatsService($this->sampleRepository(), $this->cacheDirectory, $this->queue(true));
		$service->rebuildBoard(7);
		$rebuilt = $service->getBoardStats(7);

		$this->assertFalse($rebuilt['generating']);
		$this->assertSame($inline['days'], $rebuilt['days']);
		$this->assertSame($inline['total'], $rebuilt['total']);
	}

	/** Ten boards, board 1 busiest, each one quieter than the last. */
	private function tenBoardRepository(string $today = '2026-08-06'): postStatsRepository {
		$rows = [];
		$numbers = [];

		foreach (range(1, 10) as $uid) {
			$perDay = (11 - $uid) * 10;
			$rows[$uid] = [
				['day' => '2026-08-04', 'min_no' => 1, 'max_no' => $perDay],
				['day' => '2026-08-05', 'min_no' => $perDay + 1, 'max_no' => $perDay * 2],
			];
			$numbers[$uid] = $perDay * 2;
		}

		return $this->repository($today, $rows, $numbers);
	}

	public function testEveryBoardKeepsItsOwnDaySeries(): void {
		$service = new postStatsService($this->tenBoardRepository(), $this->cacheDirectory);

		$stats = $service->getSiteStats(range(1, 10));

		// Ranked on lifetime posts, biggest first — the order colours are handed out in.
		$this->assertSame(range(1, 10), $stats['ranked']);

		// Every board is on the chart, none folded away.
		$this->assertCount(10, $stats['series']);

		// The series are positional: one value per day in the shared list, same length for all.
		$this->assertCount(count($stats['dayList']), $stats['series'][1]);
		$this->assertCount(count($stats['dayList']), $stats['series'][10]);
	}

	public function testASeriesLinesUpWithTheSharedDayList(): void {
		$service = new postStatsService($this->tenBoardRepository(), $this->cacheDirectory);
		$stats = $service->getSiteStats(range(1, 10));

		$byDay = array_combine($stats['dayList'], $stats['series'][1]);

		// Board 1 posts 100 a day; day two is the gap from 100 to 200.
		$this->assertSame(100, $byDay['2026-08-04']);
		$this->assertSame(100, $byDay['2026-08-05']);
	}

	public function testEveryPostIsAccountedForAcrossTheBoards(): void {
		$service = new postStatsService($this->tenBoardRepository(), $this->cacheDirectory);
		$stats = $service->getSiteStats(range(1, 10));

		$position = array_search('2026-08-05', $stats['dayList'], true);
		$fromSeries = 0;
		foreach ($stats['series'] as $counts) {
			$fromSeries += $counts[$position];
		}

		// The segments of a column always add up to the column.
		$this->assertSame($stats['days']['2026-08-05'], $fromSeries);
	}

	public function testTodayIsAppendedToEveryBoardSeriesAndTheDayList(): void {
		// Each board's counter has moved past yesterday's close, so today is live.
		$rows = [];
		$numbers = [];
		foreach ([1, 2] as $uid) {
			$rows[$uid] = [['day' => '2026-08-05', 'min_no' => 1, 'max_no' => 10]];
			$numbers[$uid] = 10 + $uid;
		}

		$stats = (new postStatsService($this->repository('2026-08-06', $rows, $numbers), $this->cacheDirectory))
			->getSiteStats([1, 2]);

		$this->assertSame('2026-08-06', end($stats['dayList']));
		$this->assertSame(1, end($stats['series'][1]));
		$this->assertSame(2, end($stats['series'][2]));
		$this->assertSame(3, $stats['days']['2026-08-06']);
	}

	public function testExtendingTheCacheKeepsEveryBoardSeriesAlignedToTheDayList(): void {
		$service = new postStatsService($this->tenBoardRepository('2026-08-06'), $this->cacheDirectory);
		$service->getSiteStats(range(1, 10));

		// The day turns over: 2026-08-06 completes and a new day begins.
		$rows = [];
		$numbers = [];
		foreach (range(1, 10) as $uid) {
			$perDay = (11 - $uid) * 10;
			$rows[$uid] = [
				['day' => '2026-08-04', 'min_no' => 1, 'max_no' => $perDay],
				['day' => '2026-08-05', 'min_no' => $perDay + 1, 'max_no' => $perDay * 2],
				['day' => '2026-08-06', 'min_no' => $perDay * 2 + 1, 'max_no' => $perDay * 3],
			];
			$numbers[$uid] = $perDay * 3;
		}

		$stats = (new postStatsService($this->repository('2026-08-07', $rows, $numbers), $this->cacheDirectory))
			->getSiteStats(range(1, 10));

		// Appending a day must lengthen every board's series by exactly one, or the positions
		// stop meaning what the day list says they mean.
		foreach ($stats['series'] as $uid => $counts) {
			$this->assertCount(count($stats['dayList']), $counts, "board {$uid}");
		}

		$byDay = array_combine($stats['dayList'], $stats['series'][1]);
		$this->assertSame(100, $byDay['2026-08-04']);
		$this->assertSame(100, $byDay['2026-08-06']);
	}

	public function testSiteCacheIsRebuiltWhenTheBoardSetChanges(): void {
		$repository = $this->repository(
			'2026-08-06',
			[
				7 => [['day' => '2026-08-05', 'min_no' => 1, 'max_no' => 45]],
				8 => [['day' => '2026-08-05', 'min_no' => 100, 'max_no' => 200]],
			],
			[7 => 50, 8 => 210]
		);

		$service = new postStatsService($repository, $this->cacheDirectory);
		$service->getSiteStats([7]);
		$stats = $service->getSiteStats([7, 8]);

		$this->assertSame(45 + 200, $stats['days']['2026-08-05']);
		$this->assertCount(2, $stats['boards']);
	}
}
