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
	 * low and high post number of that day, which is the row shape the real query returns.
	 */
	private function repository(
		string $today,
		array $dailyRows,
		array $currentNumbers,
		array $counterHistory = []
	): postStatsRepository {
		return new class($today, $dailyRows, $currentNumbers, $counterHistory) extends postStatsRepository {
			public int $dailyQueryCount = 0;
			public array $recorded = [];

			public function __construct(
				private string $today,
				private array $dailyRows,
				private array $currentNumbers,
				private array $counterHistory,
			) {}

			/** @return array Rows of ['board_uid', 'day', 'post_number']. */
			public function getCounterHistory(array $boardUids): array {
				$rows = [];
				foreach ($this->counterHistory as $uid => $days) {
					if (!in_array($uid, $boardUids, true)) {
						continue;
					}
					foreach ($days as $day => $number) {
						$rows[] = ['board_uid' => $uid, 'day' => $day, 'post_number' => $number];
					}
				}

				return $rows;
			}

			public function recordCounterHistory(array $numbers, string $day): void {
				$this->recorded[$day] = $numbers;
			}

			public function getSnapshot(array $boardUids): array {
				return [
					'today' => $this->today,
					'numbers' => array_intersect_key($this->currentNumbers, array_flip($boardUids)),
				];
			}

			public function getDailySeries(int $boardUid, string $fromDay = ''): array {
				$this->dailyQueryCount++;

				return $this->window($boardUid, $fromDay);
			}

			public function getDailySeriesForBoards(array $boardUids, string $fromDay = ''): array {
				$this->dailyQueryCount++;
				$rows = [];

				// ORDER BY board_uid, day — grouped by board, oldest first within each.
				foreach ($boardUids as $uid) {
					foreach ($this->window($uid, $fromDay) as $row) {
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

	public function testAPurgedBacklogIsSpreadOverTheDaysItMustHaveHappenedOn(): void {
		// Nothing below no. 1000 survives — an old sweep took the lot — on a board created ten
		// days before its oldest surviving post.
		$repository = $this->repository(
			'2026-08-06',
			[7 => [['day' => '2026-08-05', 'min_no' => 1000, 'max_no' => 1010]]],
			[7 => 1010]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-07-26');

		// The day itself keeps only what it can account for, not the whole backlog.
		$this->assertLessThan(200, $stats['days']['2026-08-05']);
		// The 999 numbers whose posts are gone are spread over the ten days they must fall in.
		$this->assertSame(10, count(array_filter($stats['days'], fn($d) => $d > 0)));
		// And none of them is lost.
		$this->assertSame(1010, array_sum($stats['days']));
		$this->assertSame(1010, $stats['total']);
	}

	public function testWithoutACreationDateAPurgedBacklogIsLeftUnattributed(): void {
		// No creation date means no lower bound on when the pruned posts were made. They stay in
		// the total, but nothing claims to know which day they belong to.
		$repository = $this->repository(
			'2026-08-06',
			[7 => [['day' => '2026-08-05', 'min_no' => 1000, 'max_no' => 1010]]],
			[7 => 1010]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame(11, $stats['days']['2026-08-05']);
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
		// The day is credited with the posts it can account for, not with the 7,999 before them.
		$this->assertSame(1001, $stats['days']['2026-08-05']);
		$this->assertSame(500, $stats['todayCount']);
	}

	public function testTodaysCounterIsRecordedOnTheWayPast(): void {
		$repository = $this->sampleRepository();

		(new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		// Today gets a reading of its own, so once it is history no amount of pruning can take
		// its activity away.
		$this->assertSame([7 => 50], $repository->recorded['2026-08-06']);
	}

	public function testARecordedReadingAnchorsADayWhosePostsAreAllGone(): void {
		// Two days survive; the three between them were pruned outright, but readings were taken
		// on each of them at the time.
		$repository = $this->repository(
			'2026-08-10',
			[7 => [
				['day' => '2026-08-05', 'min_no' => 1, 'max_no' => 100],
				['day' => '2026-08-09', 'min_no' => 400, 'max_no' => 500],
			]],
			[7 => 500],
			[7 => ['2026-08-06' => 200, '2026-08-07' => 250, '2026-08-08' => 390]]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-04');

		// Each pruned day reports what its own reading says, not an average of the whole gap.
		$this->assertSame(100, $stats['days']['2026-08-06']);
		$this->assertSame(50, $stats['days']['2026-08-07']);
		$this->assertSame(140, $stats['days']['2026-08-08']);
		$this->assertSame(110, $stats['days']['2026-08-09']);
		$this->assertSame(500, array_sum(array_diff_key($stats['days'], ['2026-08-10' => 0])));
	}

	public function testAReadingBeatsTheEstimateItWouldOtherwiseGet(): void {
		// The same gap without any readings: 400 posts spread flat over four days.
		$repository = $this->repository(
			'2026-08-10',
			[7 => [
				['day' => '2026-08-05', 'min_no' => 1, 'max_no' => 100],
				['day' => '2026-08-09', 'min_no' => 400, 'max_no' => 500],
			]],
			[7 => 500]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-04');

		$this->assertSame(100, $stats['days']['2026-08-06']);
		$this->assertSame(100, $stats['days']['2026-08-07']);
		$this->assertSame(100, $stats['days']['2026-08-08']);
		$this->assertSame(100, $stats['days']['2026-08-09']);
	}

	public function testAPrunedTailIsSpreadRatherThanPiledOntoToday(): void {
		// The board's surviving posts stop three months in; the counter has run on to 500,000
		// since. Without a closing reading all of that lands on today, and tomorrow it becomes
		// the first recorded day carrying years of posts.
		$rows = [];
		$no = 0;
		for ($d = new \DateTimeImmutable('2020-01-02'); $d->format('Y-m-d') <= '2020-03-31'; $d = $d->modify('+1 day')) {
			$min = $no + 1;
			$no += 133;
			$rows[] = ['day' => $d->format('Y-m-d'), 'min_no' => $min, 'max_no' => $no];
		}

		$repository = $this->repository('2026-08-13', [7 => $rows], [7 => 500000]);
		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2020-01-01');

		// Today is an ordinary day, not a dumping ground.
		$this->assertLessThan(1000, $stats['todayCount']);
		// The years with no surviving posts carry the activity instead.
		$this->assertGreaterThan(2000, count(array_filter($stats['days'])));
		$this->assertSame(500000, $stats['total']);
	}

	public function testAHealthyBoardsTodayIsStillTheExactCounterDifference(): void {
		// Posts survive right up to yesterday, so there is no tail to spread and today stays
		// exactly what the counter says it is.
		$stats = (new postStatsService($this->sampleRepository(), $this->cacheDirectory))->getBoardStats(7, '2026-08-02');

		$this->assertSame(5, $stats['todayCount']);
	}

	public function testTheThreeKindsOfEvidenceLayerInOrder(): void {
		// A real pruned board's shape, end to end:
		//   created 2015-01-01, nothing survives from then
		//   one sticky left over from 2015-03-01 (no. 50)
		//   eleven years pruned away entirely
		//   dense rows from 2026-08-01 to 2026-08-12, from before the readings table existed
		//   recorded readings from 2026-08-13, the day the table went in
		//   today is 2026-08-20, counter at 500,000
		$rows = [['day' => '2015-03-01', 'min_no' => 50, 'max_no' => 50]];
		$no = 480000;
		for ($d = new \DateTimeImmutable('2026-08-01'); $d->format('Y-m-d') <= '2026-08-12'; $d = $d->modify('+1 day')) {
			$min = $no + 1;
			$no += 100;
			$rows[] = ['day' => $d->format('Y-m-d'), 'min_no' => $min, 'max_no' => $no];
		}

		$recorded = [];
		$at = $no;
		foreach (['2026-08-13' => 300, '2026-08-14' => 50, '2026-08-15' => 900,
		          '2026-08-16' => 120, '2026-08-17' => 120, '2026-08-18' => 120,
		          '2026-08-19' => 120] as $day => $made) {
			$at += $made;
			$recorded[$day] = $at;
		}

		$repository = $this->repository('2026-08-20', [7 => $rows], [7 => 500000], [7 => $recorded]);
		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2015-01-01');
		$days = $stats['days'];

		// 1. Creation to the sticky: 50 numbers spread flat over the 59 days between.
		$this->assertSame(0, $days['2015-01-02']);
		$this->assertSame(1, $days['2015-03-01']);

		// 2. The pruned decade: flat, and nowhere near a spike.
		$this->assertSame($days['2016-06-15'], $days['2020-01-01']);
		$this->assertLessThan(200, $days['2020-01-01']);

		// 3. Days whose posts survive report their own real figures, not an average.
		$this->assertSame(100, $days['2026-08-05']);
		$this->assertSame(100, $days['2026-08-12']);

		// 4. Once the table has readings, they win outright — including a spike that really
		//    happened and a quiet day that really was quiet.
		$this->assertSame(300, $days['2026-08-13']);
		$this->assertSame(50, $days['2026-08-14']);
		$this->assertSame(900, $days['2026-08-15']);

		// 5. Every number is still accounted for, once.
		$this->assertSame(500000, array_sum($days));
		$this->assertSame(500000, $stats['total']);
	}

	public function testABoardOlderThanItsOwnRowDoesNotPileItsPrefixOntoDayOne(): void {
		// The row says created 2024, but posts from 2020 survive — so the row is wrong, and the
		// 4,999 numbers below the oldest surviving post have no knowable date.
		$repository = $this->repository(
			'2026-08-20',
			[7 => [['day' => '2020-01-01', 'min_no' => 5000, 'max_no' => 5900]]],
			[7 => 6000]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2024-06-01');

		// The day is credited with its own 901 posts, not with the 4,999 before them.
		$this->assertSame(901, $stats['days']['2020-01-01']);
		// The counter still reports every post the board ever made.
		$this->assertSame(6000, $stats['total']);
	}

	public function testCreatedAndFirstPostedTheSameDayStartsFromZero(): void {
		// Nothing came before, so the whole of that day's numbering really was made that day.
		$repository = $this->repository(
			'2026-08-20',
			[7 => [['day' => '2026-08-01', 'min_no' => 1, 'max_no' => 900]]],
			[7 => 900]
		);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-01');

		$this->assertSame(900, $stats['days']['2026-08-01']);
		$this->assertSame(900, array_sum($stats['days']));
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

		// With no creation dates supplied, each board is credited with the posts it can account
		// for: board 7 from no. 1, board 8 from no. 100.
		$this->assertSame(45 + 101, $stats['days']['2026-08-05']);
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

		$this->assertSame(45 + 101, $stats['days']['2026-08-05']);
		$this->assertCount(2, $stats['boards']);
	}
}
