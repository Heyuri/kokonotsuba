<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\postStats\postStatsBuildQueue;
use Kokonotsuba\Modules\postStats\postStatsRepository;
use Kokonotsuba\Modules\postStats\postStatsService;

/**
 * Unit tests for the post statistics service.
 *
 * The repository is a stub holding surviving posts as [board uid => [no => day]], from which
 * it derives the runs the real query returns.
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

	private function repository(string $today, array $posts, array $counters, array $history = []): postStatsRepository {
		return new class($today, $posts, $counters, $history) extends postStatsRepository {
			public array $recorded = [];
			public array $runQueries = [];

			public function __construct(
				public string $today,
				public array $posts,
				public array $counters,
				public array $history,
			) {}

			public function getCounterHistory(array $boardUids): array {
				$rows = [];
				foreach ($this->history as $uid => $days) {
					if (in_array($uid, $boardUids, true)) {
						foreach ($days as $day => $number) {
							$rows[] = ['board_uid' => $uid, 'day' => $day, 'post_number' => $number];
						}
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
					'secondsToday' => 43200,
					'numbers' => array_intersect_key($this->counters, array_flip($boardUids)),
				];
			}

			public function getRuns(array $cuts): array {
				$this->runQueries[] = $cuts;
				$rows = [];

				foreach ($cuts as $uid => $cutNo) {
					$posts = $this->posts[$uid] ?? [];
					ksort($posts);

					$open = null;
					foreach ($posts as $no => $day) {
						if ($no <= $cutNo) {
							continue;
						}
						if ($open !== null && $open['last_no'] === $no - 1 && $open['day'] === $day) {
							$open['last_no'] = $no;
							continue;
						}
						if ($open !== null) {
							$rows[] = $open;
						}
						$open = ['board_uid' => $uid, 'day' => $day, 'first_no' => $no, 'last_no' => $no];
					}
					if ($open !== null) {
						$rows[] = $open;
					}
				}

				return $rows;
			}
		};
	}

	/** [no => day] for consecutive numbers. */
	private function posts(int $from, int $to, string $day): array {
		return array_fill_keys(range($from, $to), $day);
	}

	/**
	 * Board 7: 1-10 on the 3rd, 11-30 on the 4th with 14-20 purged, 31-45 on the 5th, and
	 * 46-50 today with 49 purged.
	 */
	private function sampleRepository(string $today = '2026-08-06'): postStatsRepository {
		$posts = $this->posts(1, 10, '2026-08-03')
			+ $this->posts(11, 13, '2026-08-04') + $this->posts(21, 30, '2026-08-04')
			+ $this->posts(31, 45, '2026-08-05')
			+ $this->posts(46, 48, '2026-08-06') + $this->posts(50, 50, '2026-08-06');

		return $this->repository($today, [7 => $posts], [7 => 50]);
	}

	public function testPurgedPostsInsideADayCountOnThatDay(): void {
		$stats = (new postStatsService($this->sampleRepository(), $this->cacheDirectory))->getBoardStats(7, '2026-08-03');

		$this->assertSame(10, $stats['days']['2026-08-03']);
		$this->assertSame(20, $stats['days']['2026-08-04']);
		$this->assertSame(15, $stats['days']['2026-08-05']);
		$this->assertSame(5, $stats['todayCount']);
		$this->assertSame(0, $stats['undated']);
		$this->assertSame(50, $stats['total']);
	}

	public function testAPurgedStretchAcrossDaysIsUndatedRatherThanSpread(): void {
		$posts = $this->posts(1, 10, '2026-08-01') + $this->posts(41, 50, '2026-08-05');
		$repository = $this->repository('2026-08-06', [7 => $posts], [7 => 50]);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-01');

		$this->assertSame(['2026-08-01' => 10, '2026-08-05' => 10], $stats['days']);
		$this->assertSame(30, $stats['undated']);
		$this->assertSame(50, $stats['total']);
	}

	public function testABoardOlderThanItsCreationDateLeavesItsPrefixUndated(): void {
		$repository = $this->repository('2026-08-06', [7 => $this->posts(101, 110, '2026-08-02')], [7 => 110]);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-04');

		$this->assertSame(['2026-08-02' => 10], $stats['days']);
		$this->assertSame(100, $stats['undated']);
		$this->assertSame('2026-08-02', $stats['startDay']);
	}

	public function testReadingsDateADayWhosePostsAreAllGone(): void {
		$posts = $this->posts(1, 10, '2026-08-01') + $this->posts(31, 40, '2026-08-04');
		$history = [7 => ['2026-08-01' => 10, '2026-08-02' => 10, '2026-08-03' => 30]];
		$repository = $this->repository('2026-08-06', [7 => $posts], [7 => 40], $history);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-01');

		// 11 to 30 came after the reading on the 2nd and by the reading on the 3rd, which is a
		// span of two days, so they stay undated.
		$this->assertSame(['2026-08-01' => 10, '2026-08-04' => 10], $stats['days']);
		$this->assertSame(20, $stats['undated']);
	}

	public function testTodaysCounterIsRecordedOnTheWayPast(): void {
		$repository = $this->sampleRepository();
		(new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame([7 => 50], $repository->recorded['2026-08-06']);
	}

	public function testALaterViewOnlyReadsPastTheCommittedCut(): void {
		$repository = $this->sampleRepository();
		(new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-03');
		(new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-03');

		$this->assertSame([7 => 0], $repository->runQueries[0]);
		$this->assertSame([7 => 45], $repository->runQueries[1]);
	}

	public function testCommittedDaysSurviveTheirPostsBeingPurged(): void {
		$repository = $this->sampleRepository();
		(new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-03');

		// The next day: everything before today's posts is purged, and 51-55 are posted.
		$repository->today = '2026-08-07';
		$repository->posts = [7 => $this->posts(46, 48, '2026-08-06') + $this->posts(50, 50, '2026-08-06') + $this->posts(51, 55, '2026-08-07')];
		$repository->counters = [7 => 55];

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7, '2026-08-03');

		$this->assertSame(10, $stats['days']['2026-08-03']);
		$this->assertSame(20, $stats['days']['2026-08-04']);
		$this->assertSame(15, $stats['days']['2026-08-05']);
		$this->assertSame(5, $stats['days']['2026-08-06']);
		$this->assertSame(5, $stats['todayCount']);
		$this->assertSame(55, $stats['total']);
	}

	public function testBoardWithNoPostsReportsZeroesRatherThanFailing(): void {
		$repository = $this->repository('2026-08-06', [], [7 => 0]);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$this->assertSame([], $stats['days']);
		$this->assertSame(0, $stats['total']);
		$this->assertSame(0, $stats['todayCount']);
	}

	private function siteRepository(): postStatsRepository {
		return $this->repository(
			'2026-08-06',
			[
				1 => $this->posts(1, 5, '2026-08-04') + $this->posts(6, 8, '2026-08-06'),
				2 => $this->posts(1, 3, '2026-08-02') + $this->posts(4, 9, '2026-08-05'),
			],
			[1 => 8, 2 => 9]
		);
	}

	public function testSiteTotalsSumTheBoardsDayByDay(): void {
		$stats = (new postStatsService($this->siteRepository(), $this->cacheDirectory))
			->getSiteStats([1, 2], [1 => '2026-08-04', 2 => '2026-08-02']);

		$this->assertSame(['2026-08-02' => 3, '2026-08-04' => 5, '2026-08-05' => 6, '2026-08-06' => 3], $stats['days']);
		$this->assertSame(17, $stats['total']);
		$this->assertSame(3, $stats['todayCount']);
		$this->assertSame('2026-08-02', $stats['startDay']);
	}

	public function testEveryBoardSeriesLinesUpWithTheDayList(): void {
		$stats = (new postStatsService($this->siteRepository(), $this->cacheDirectory))
			->getSiteStats([1, 2], [1 => '2026-08-04', 2 => '2026-08-02']);

		$this->assertSame(['2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06'], $stats['dayList']);
		$this->assertSame([0, 0, 5, 0, 3], $stats['series'][1]);
		$this->assertSame([3, 0, 0, 6, 0], $stats['series'][2]);
		$this->assertSame([2, 1], $stats['ranked']);
	}

	public function testSiteCacheIsRebuiltWhenTheBoardSetChanges(): void {
		$repository = $this->siteRepository();
		(new postStatsService($repository, $this->cacheDirectory))->getSiteStats([1, 2]);

		$stats = (new postStatsService($repository, $this->cacheDirectory))->getSiteStats([1]);

		$this->assertSame([1], array_keys($stats['boards']));
		$this->assertSame(8, $stats['total']);
	}

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

		$this->assertTrue($stats['generating']);
		$this->assertSame(['board-7'], $queue->requests);
		$this->assertSame([], $repository->runQueries);
	}

	public function testBuildRunsInlineWhenTheQueueCannotTakeIt(): void {
		$stats = (new postStatsService($this->sampleRepository(), $this->cacheDirectory, $this->queue(false)))
			->getBoardStats(7, '2026-08-03');

		$this->assertFalse($stats['generating']);
		$this->assertSame(50, $stats['total']);
	}

	public function testAnExistingCacheIsNeverDeferred(): void {
		$repository = $this->sampleRepository();
		(new postStatsService($repository, $this->cacheDirectory))->getBoardStats(7);

		$repository->today = '2026-08-08';
		$queue = $this->queue(true);
		$stats = (new postStatsService($repository, $this->cacheDirectory, $queue))->getBoardStats(7);

		$this->assertFalse($stats['generating']);
		$this->assertSame([], $queue->requests);
	}

	public function testTheBackgroundRebuildMatchesAnInlineBuild(): void {
		(new postStatsService($this->sampleRepository(), $this->cacheDirectory))->rebuildBoard(7, '2026-08-03');
		$rebuilt = (new postStatsService($this->sampleRepository(), $this->cacheDirectory, $this->queue(true)))
			->getBoardStats(7, '2026-08-03');

		$this->tearDown();
		$inline = (new postStatsService($this->sampleRepository(), $this->cacheDirectory))->getBoardStats(7, '2026-08-03');

		$this->assertSame($inline, $rebuilt);
	}
}
