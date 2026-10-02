<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\Modules\postStats\datePosts;

/**
 * Unit tests for datePosts(): which numbers can be put on a day, and which cannot.
 */
final class PostStatsLedgerTest extends TestCase {
	protected function setUp(): void {
		requireModuleFile('postStats/postStatsLedger.php');
	}

	private function span(string $first, int $from, int $to, string $day = ''): array {
		return ['first' => $from, 'last' => $to, 'day' => $day ?: $first];
	}

	private function date(array $runs, array $readings = [], int $counter = 0, ?string $opening = null, string $today = '2026-08-10'): array {
		return datePosts($runs, $readings, 0, $opening, $counter, $today, '2026-08-09');
	}

	public function testSurvivorsCountOnTheirOwnDay(): void {
		$result = $this->date([$this->span('2026-08-01', 1, 10), $this->span('2026-08-02', 11, 15)], [], 15, '2026-08-01');

		$this->assertSame(['2026-08-01' => 10, '2026-08-02' => 5], $result['committed']['days']);
		$this->assertSame(0, $result['committed']['undated']);
	}

	public function testAGapInsideOneDayIsDatedToThatDay(): void {
		// 4 to 7 were purged, but 3 and 8 were both made on the 1st.
		$result = $this->date([$this->span('2026-08-01', 1, 3), $this->span('2026-08-01', 8, 10)], [], 10, '2026-08-01');

		$this->assertSame(['2026-08-01' => 10], $result['committed']['days']);
	}

	public function testAGapAcrossDaysIsUndatedRatherThanSpread(): void {
		$result = $this->date([$this->span('2026-08-01', 1, 3), $this->span('2026-08-04', 8, 10)], [], 10, '2026-08-01');

		$this->assertSame(['2026-08-01' => 3, '2026-08-04' => 3], $result['committed']['days']);
		$this->assertSame(4, $result['committed']['undated']);
	}

	public function testAReadingSplitsAGapIntoDatedHalves(): void {
		// The counter stood at 5 at some point on the 1st and the next survivor is on the 2nd: 4
		// and 5 belong to the 1st, 6 and 7 are between the reading and the 2nd.
		$result = $this->date(
			[$this->span('2026-08-01', 1, 3), $this->span('2026-08-02', 8, 10)],
			['2026-08-01' => 5],
			10,
			'2026-08-01'
		);

		$this->assertSame(['2026-08-01' => 5, '2026-08-02' => 3], $result['committed']['days']);
		$this->assertSame(2, $result['committed']['undated']);
	}

	public function testReadingsOnBothSidesDateADayWithNothingLeft(): void {
		$result = $this->date([], ['2026-08-01' => 10, '2026-08-02' => 25, '2026-08-03' => 25], 25, '2026-08-01');

		$this->assertSame(10, $result['committed']['days']['2026-08-01']);
		$this->assertSame(0, $result['committed']['days']['2026-08-02'] ?? 0);
		$this->assertSame(15, $result['committed']['undated']);
	}

	public function testWithoutAnOpeningTheEarliestNumbersAreUndated(): void {
		$result = $this->date([$this->span('2026-08-03', 51, 60)], [], 60);

		$this->assertSame(['2026-08-03' => 10], $result['committed']['days']);
		$this->assertSame(50, $result['committed']['undated']);
	}

	public function testTheCreationDayDatesAPrefixMadeTheSameDay(): void {
		$result = $this->date([$this->span('2026-08-03', 51, 60)], [], 60, '2026-08-03');

		$this->assertSame(['2026-08-03' => 60], $result['committed']['days']);
	}

	public function testTodayStaysPendingAndThePurgedTailOfTodayIsDated(): void {
		$result = $this->date([$this->span('2026-08-09', 1, 5), $this->span('2026-08-10', 6, 8)], [], 10, '2026-08-09');

		$this->assertSame(['2026-08-09' => 5], $result['committed']['days']);
		$this->assertSame(5, $result['committed']['cutNo']);
		$this->assertSame('2026-08-09', $result['committed']['cutDay']);
		$this->assertSame(['2026-08-10' => 5], $result['pending']['days']);
		$this->assertSame(0, $result['pending']['undated']);
	}

	public function testAMovedThreadIsCountedButNotTrustedAsEvidence(): void {
		// 11 to 13 arrived in a move on the 5th carrying their old timestamps from the 1st.
		$result = $this->date(
			[$this->span('2026-08-05', 1, 10), $this->span('2026-08-01', 11, 13), $this->span('2026-08-05', 16, 20)],
			[],
			20,
			'2026-08-05'
		);

		$this->assertSame(3, $result['committed']['days']['2026-08-01']);
		// 14 and 15 sit between survivors from the 5th, so they are the 5th's.
		$this->assertSame(17, $result['committed']['days']['2026-08-05']);
		$this->assertSame(0, $result['committed']['undated']);
	}

	public function testEveryNumberIsAccountedForExactlyOnce(): void {
		$runs = [
			$this->span('2026-08-01', 3, 7),
			$this->span('2026-08-03', 12, 12),
			$this->span('2026-08-03', 20, 30),
			$this->span('2026-08-10', 41, 44),
		];
		$result = $this->date($runs, ['2026-08-05' => 35], 50);

		$sum = array_sum($result['committed']['days']) + $result['committed']['undated']
			+ array_sum($result['pending']['days']) + $result['pending']['undated'];

		$this->assertSame(50, $sum);
	}

	public function testResumingFromACutGivesTheSameDaysAsOnePass(): void {
		$runs = [$this->span('2026-08-01', 1, 5), $this->span('2026-08-02', 6, 9), $this->span('2026-08-03', 12, 15)];

		$whole = datePosts($runs, [], 0, '2026-08-01', 15, '2026-08-04', '2026-08-03');
		$first = datePosts(array_slice($runs, 0, 2), [], 0, '2026-08-01', 9, '2026-08-03', '2026-08-02');
		$second = datePosts(
			[$runs[2]],
			[],
			$first['committed']['cutNo'],
			$first['committed']['cutDay'],
			15,
			'2026-08-04',
			'2026-08-03'
		);

		$this->assertSame(
			$whole['committed']['days'],
			array_merge($first['committed']['days'], $second['committed']['days'])
		);
		$this->assertSame($whole['committed']['undated'], $first['committed']['undated'] + $second['committed']['undated']);
	}
}
