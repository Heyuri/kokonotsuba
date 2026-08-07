<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\postStats\postStatsRenderer;
use Kokonotsuba\template\templateEngine;

/**
 * Unit tests for the post statistics renderer: gap filling, the day/week/month grouping that
 * keeps a long history inside the bar budget, and the rates shown in the tiles.
 *
 * The markup comes from the module's own templates/ directory, so these also cover the blocks
 * being found and their placeholders being filled.
 */
final class PostStatsRendererTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('postStats/postStatsRenderer.php');
	}

	/**
	 * INPUT_MAX is the one config key the engine reads without a fallback when it builds its
	 * base replacements; the module's own blocks use none of them.
	 */
	private function engine(): templateEngine {
		return new templateEngine(KOKO_TEST_ROOT . '/module/postStats/templates', [
			'config' => ['INPUT_MAX' => 0],
		]);
	}

	private function renderer(int $maxBars): postStatsRenderer {
		return new postStatsRenderer($this->engine(), $maxBars);
	}

	public function testSeriesFillsTheDaysNothingWasPostedOn(): void {
		$renderer = $this->renderer(120);

		$series = $renderer->buildSeries(
			['2026-08-01' => 5, '2026-08-04' => 2],
			'2026-08-01',
			'2026-08-04',
			0
		);

		$this->assertSame([
			'2026-08-01' => 5,
			'2026-08-02' => 0,
			'2026-08-03' => 0,
			'2026-08-04' => 2,
		], $series);
	}

	public function testSeriesIsClampedToTheRequestedSpan(): void {
		$renderer = $this->renderer(120);

		$series = $renderer->buildSeries(['2026-01-01' => 5], '2026-01-01', '2026-08-10', 30);

		$this->assertCount(30, $series);
		$this->assertSame('2026-07-12', array_key_first($series));
		$this->assertSame('2026-08-10', array_key_last($series));
	}

	public function testBoardWithNoPostsHasNoSeries(): void {
		$renderer = $this->renderer(120);

		$this->assertSame([], $renderer->buildSeries([], '', '2026-08-10', 0));
	}

	public function testDaysAreKeptAsBarsWhileTheyFitTheBudget(): void {
		$renderer = $this->renderer(10);
		$series = $renderer->buildSeries(['2026-08-01' => 3], '2026-08-01', '2026-08-05', 0);

		$buckets = $renderer->bucketSeries($series, '2026-08-05');

		$this->assertCount(5, $buckets);
		$this->assertSame('2026-08-01', $buckets[0]['label']);
	}

	public function testDaysAreGroupedIntoWeeksOnceThereAreTooManyBars(): void {
		$renderer = $this->renderer(10);
		$series = $renderer->buildSeries(['2026-08-03' => 7], '2026-08-01', '2026-08-28', 0);

		$buckets = $renderer->bucketSeries($series, '2026-08-28');

		// 28 days is over the ten-bar budget but under ten weeks.
		$this->assertCount(5, $buckets);
		$this->assertSame(7, array_sum(array_column($buckets, 'value')));
	}

	public function testLongHistoriesFallBackToMonths(): void {
		$renderer = $this->renderer(10);
		$series = $renderer->buildSeries(['2026-01-15' => 4], '2026-01-01', '2026-06-30', 0);

		$buckets = $renderer->bucketSeries($series, '2026-06-30');

		$this->assertCount(6, $buckets);
		$this->assertSame('2026-01', $buckets[0]['label']);
		$this->assertSame(4, $buckets[0]['value']);
	}

	public function testOnlyTheBucketRunningUpToTodayIsMarkedUnfinished(): void {
		$renderer = $this->renderer(120);
		$series = $renderer->buildSeries(['2026-08-01' => 1], '2026-08-01', '2026-08-03', 0);

		$buckets = $renderer->bucketSeries($series, '2026-08-03');

		$this->assertTrue($buckets[2]['partial']);
		$this->assertFalse(!empty($buckets[0]['partial']));
	}

	public function testChartScalesBarsAgainstThePeak(): void {
		$renderer = $this->renderer(120);
		$series = $renderer->buildSeries(['2026-08-01' => 10, '2026-08-02' => 5], '2026-08-01', '2026-08-02', 0);

		$html = $renderer->renderChart($renderer->bucketSeries($series, '2026-08-03'), 'Test');

		$this->assertStringContains('height:100.00%', $html);
		$this->assertStringContains('height:50.00%', $html);
	}

	public function testEmptyChartSaysSoInsteadOfDividingByZero(): void {
		$renderer = $this->renderer(120);

		// The i18n stub echoes the key, so this also asserts the text is translated rather
		// than written into the renderer.
		$this->assertStringContains('poststats_empty', $renderer->renderChart([], 'Test'));
	}

	/** Nine completed days of 10 posts each, plus a partial today. */
	private function tenADayStats(): array {
		$days = [];
		for ($i = 1; $i <= 9; $i++) {
			$days[date('Y-m-d', strtotime('2026-08-10 -' . $i . ' days'))] = 10;
		}
		$days['2026-08-10'] = 3;

		return [
			'days' => $days,
			'firstDay' => '2026-08-01',
			'today' => '2026-08-10',
			'todayCount' => 3,
			'total' => 93,
			'lastNo' => 93,
		];
	}

	public function testTheRateIsForTheSpanOnScreen(): void {
		$renderer = $this->renderer(120);
		$stats = $this->tenADayStats();

		$series = $renderer->buildSeries($stats['days'], $stats['firstDay'], $stats['today'], 0);
		$html = $renderer->renderTiles($stats, $series, 'poststats_range_all', false);

		// Nine completed days of ten. Today is still running, so it is not in the average.
		$this->assertStringContains('<dd>10.00</dd>', $html);
		$this->assertStringContains('<dd>0.42</dd>', $html);
	}

	public function testANarrowerSpanGivesItsOwnRate(): void {
		$renderer = $this->renderer(120);

		// Quiet for a week, then busy for two days.
		$days = [];
		for ($i = 9; $i >= 3; $i--) {
			$days[date('Y-m-d', strtotime('2026-08-10 -' . $i . ' days'))] = 10;
		}
		$days['2026-08-08'] = 40;
		$days['2026-08-09'] = 40;
		$days['2026-08-10'] = 3;

		$stats = [
			'days' => $days, 'firstDay' => '2026-08-01', 'today' => '2026-08-10',
			'todayCount' => 3, 'total' => 153, 'lastNo' => 153,
		];

		$whole = $renderer->buildSeries($days, '2026-08-01', '2026-08-10', 0);
		$recent = $renderer->buildSeries($days, '2026-08-01', '2026-08-10', 3);

		// Nine completed days averaging 16.67, against the last two completed averaging 40.
		$this->assertStringContains('<dd>16.67</dd>', $renderer->renderTiles($stats, $whole, 'x', false));
		$this->assertStringContains('<dd>40.00</dd>', $renderer->renderTiles($stats, $recent, 'x', false));
	}

	public function testThereIsOnlyOneRatePairRatherThanARowOfAverages(): void {
		$renderer = $this->renderer(120);
		$stats = $this->tenADayStats();
		$series = $renderer->buildSeries($stats['days'], $stats['firstDay'], $stats['today'], 0);

		$html = $renderer->renderTiles($stats, $series, 'poststats_range_all', true);

		$this->assertSame(1, substr_count($html, 'poststats_tile_per_day'));
		$this->assertSame(1, substr_count($html, 'poststats_tile_per_hour'));
		$this->assertSame(6, substr_count($html, '<dt>'));
	}

	public function testASpanOfOnlyTodayFallsBackToTodaysCount(): void {
		$renderer = $this->renderer(120);

		$stats = [
			'days' => ['2026-08-10' => 7],
			'firstDay' => '2026-08-10',
			'today' => '2026-08-10',
			'todayCount' => 7,
			'total' => 7,
			'lastNo' => 7,
		];
		$series = $renderer->buildSeries($stats['days'], $stats['firstDay'], $stats['today'], 0);

		$this->assertStringContains('<dd>7.00</dd>', $renderer->renderTiles($stats, $series, 'x', false));
	}

	public function testRangeLinksMarkTheCurrentSpan(): void {
		$renderer = $this->renderer(120);

		$html = $renderer->renderRangeLinks('koko.php?mode=module&amp;load=postStats', '90d');

		$this->assertStringContains('<span class="postStatsRangeCurrent">poststats_range_90d</span>', $html);
		$this->assertStringContains('range=all', $html);
	}

	/** Stand-in board: the renderer only asks for uid, title and URL. */
	private function board(int $uid, string $title): object {
		return new class($uid, $title) {
			public function __construct(private int $uid, private string $title) {}
			public function getBoardUID(): int { return $this->uid; }
			public function getBoardTitle(): string { return $this->title; }
			public function getBoardURL($live = false, $static = false) { return "/b{$this->uid}/"; }
		};
	}

	/** Nine boards to choose from. */
	private function nineBoards(): array {
		return array_map(fn($uid) => $this->board($uid, "Board {$uid}"), range(1, 9));
	}

	public function testEveryListedBoardGetsAnIdentity(): void {
		$boards = $this->nineBoards();

		$series = (new postStatsRenderer($this->engine(), 120))->assignSeries(range(1, 9), $boards);

		// Nothing is folded away — all nine are on the chart.
		$this->assertCount(9, $series);
		$this->assertSame(['1', '2', '3', '4', '5', '6', '7', '8', '1'], array_column($series, 'hue'));
	}

	public function testTheNinthBoardReusesAHueButTakesADifferentPattern(): void {
		$boards = $this->nineBoards();

		$series = (new postStatsRenderer($this->engine(), 120))->assignSeries(range(1, 9), $boards);

		// Board 9 shares board 1's hue — there are only eight that stay apart — so its identity
		// is carried by the pattern instead.
		$this->assertSame($series[1]['hue'], $series[9]['hue']);
		$this->assertSame('0', $series[1]['tier']);
		$this->assertSame('1', $series[9]['tier']);
		$this->assertNotSame(
			$series[1]['hue'] . '/' . $series[1]['tier'],
			$series[9]['hue'] . '/' . $series[9]['tier']
		);
	}

	public function testIdentitiesAreHandedOutInTheOrderTheServiceRankedThem(): void {
		$boards = $this->nineBoards();
		$renderer = new postStatsRenderer($this->engine(), 120);

		$series = $renderer->assignSeries([4, 2, 9, 1, 7, 3], $boards);

		$this->assertSame([4, 2, 9, 1, 7, 3], array_keys($series));
		$this->assertSame(['1', '2', '3', '4', '5', '6'], array_column($series, 'hue'));
		$this->assertSame('Board 4', $series[4]['label']);
	}

	public function testABoardThatNoLongerExistsIsSkippedWithoutShiftingTheRest(): void {
		$boards = $this->nineBoards();
		$renderer = new postStatsRenderer($this->engine(), 120);

		// uid 99 was unlisted between the cache being written and the page being drawn.
		$series = $renderer->assignSeries([1, 99, 2], $boards);

		$this->assertSame([1, 2], array_keys($series));
		$this->assertSame(['1', '2'], array_column($series, 'hue'));
	}

	public function testStackSegmentsLandInTheSameBucketsAsTheTotals(): void {
		$renderer = new postStatsRenderer($this->engine(), 10);

		// 28 days forces weekly buckets; both boards must be folded the same way.
		$days = [];
		$a = [];
		$b = [];
		for ($i = 0; $i < 28; $i++) {
			$day = date('Y-m-d', strtotime('2026-08-01 +' . $i . ' days'));
			$a[$day] = 2;
			$b[$day] = 3;
			$days[$day] = 5;
		}

		$series = $renderer->buildSeries($days, '2026-08-01', '2026-08-28', 0);
		$dayList = array_keys($days);
		$buckets = $renderer->bucketStack(
			$series,
			$dayList,
			[7 => array_values($a), 8 => array_values($b)],
			'2026-08-28'
		);

		$this->assertCount(5, $buckets);
		foreach ($buckets as $bucket) {
			// Every column's segments add up to the column's own total.
			$this->assertSame($bucket['value'], array_sum($bucket['segments']));
		}
	}

	public function testDaysOutsideTheSelectedSpanAreLeftOutOfTheStack(): void {
		$renderer = new postStatsRenderer($this->engine(), 120);

		$days = ['2026-08-03' => 5, '2026-08-04' => 5];
		$series = $renderer->buildSeries($days, '2026-08-03', '2026-08-04', 0);

		// The board's series also covers an older day the range does not reach.
		$buckets = $renderer->bucketStack(
			$series,
			['2026-01-01', '2026-08-03', '2026-08-04'],
			[7 => [99, 5, 0]],
			'2026-08-04'
		);

		$this->assertCount(2, $buckets);
		$this->assertSame([7 => 5], $buckets[0]['segments']);
		$this->assertSame([], $buckets[1]['segments']);
	}

	public function testEverySpanNamesATranslationKeyRatherThanItsText(): void {
		foreach (postStatsRenderer::RANGES as $key => $range) {
			$this->assertMatchesRegex('/^poststats_range_/', $range['label'], "span {$key}");
		}
	}
}
