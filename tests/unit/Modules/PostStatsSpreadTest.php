<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\Modules\postStats\spreadReadings;

/**
 * Unit tests for turning counter readings into daily counts.
 *
 * The case that matters is a board whose old posts have been pruned: the numbers are still known
 * from the counter, but the days they were made on are not, and they must not all land on
 * whichever day still has the oldest surviving post.
 */
final class PostStatsSpreadTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('postStats/postStatsSpread.php');
	}

	public function testConsecutiveReadingsAreJustTheDifference(): void {
		$days = spreadReadings([
			'2026-08-01' => 0,
			'2026-08-02' => 10,
			'2026-08-03' => 25,
		]);

		$this->assertSame(['2026-08-02' => 10, '2026-08-03' => 15], $days);
	}

	public function testAGapIsSpreadOverTheDaysItMustHaveHappenedOn(): void {
		// Nothing survives between the two readings, but 40 numbers were handed out.
		$days = spreadReadings(['2026-08-01' => 0, '2026-08-05' => 40]);

		$this->assertSame([
			'2026-08-02' => 10,
			'2026-08-03' => 10,
			'2026-08-04' => 10,
			'2026-08-05' => 10,
		], $days);
	}

	public function testTheWholeBacklogDoesNotLandOnTheFirstSurvivingDay(): void {
		// A board created two years ago that has made 500,000 posts, of which only the last
		// day's survive. Counting from zero on that day would report 500,000 posts in a day.
		$days = spreadReadings([
			'2024-08-08' => 0,
			'2026-08-07' => 499000,
			'2026-08-08' => 500000,
		]);

		$this->assertSame(1000, $days['2026-08-08']);
		$this->assertLessThan(1000, $days['2026-08-07']);
		// Every number is still counted, just placed where it must have fallen.
		$this->assertSame(500000, array_sum($days));
	}

	public function testEveryNumberIsPlacedExactlyOnceWhenTheDivisionIsUneven(): void {
		// 10 posts over 3 days does not divide; nothing may be rounded away.
		$days = spreadReadings(['2026-08-01' => 0, '2026-08-04' => 10]);

		$this->assertSame(10, array_sum($days));
		$this->assertCount(3, $days);
		// The odd ones sit nearest the reading that closed the gap.
		$this->assertSame([3, 3, 4], array_values($days));
	}

	public function testAReadingIsNeverAllowedToRunBackwards(): void {
		// A day's surviving posts only ever give a lower bound; a later one reading lower than an
		// earlier one is missing evidence, not negative activity.
		$days = spreadReadings([
			'2026-08-01' => 100,
			'2026-08-02' => 50,
			'2026-08-03' => 120,
		]);

		$this->assertSame(0, $days['2026-08-02']);
		$this->assertSame(20, $days['2026-08-03']);
	}

	public function testASingleReadingSaysNothing(): void {
		$this->assertSame([], spreadReadings(['2026-08-01' => 500]));
		$this->assertSame([], spreadReadings([]));
	}

	public function testReadingsOutOfOrderAreSortedFirst(): void {
		$days = spreadReadings(['2026-08-03' => 30, '2026-08-01' => 0, '2026-08-02' => 10]);

		$this->assertSame(['2026-08-02' => 10, '2026-08-03' => 20], $days);
	}
}
