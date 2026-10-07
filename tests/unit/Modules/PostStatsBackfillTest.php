<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\Modules\postStats\backfillReadings;

/** Readings for the days before they were recorded. */
final class PostStatsBackfillTest extends TestCase {
	protected function setUp(): void {
		requireModuleFile('postStats/postStatsBackfill.php');
	}

	public function testEachDayBeforeTheCutoffGetsItsHighestNumber(): void {
		$days = [
			'2026-09-01' => ['min' => 1, 'max' => 10],
			'2026-09-03' => ['min' => 14, 'max' => 20],
			'2026-09-05' => ['min' => 25, 'max' => 30],
		];

		$this->assertSame(['2026-09-01' => 10, '2026-09-03' => 20], backfillReadings($days, '2026-09-05'));
	}

	public function testAMovedThreadDoesNotDateLaterNumbers(): void {
		// The 2nd holds a moved thread numbered past the 3rd's posts.
		$days = [
			'2026-09-01' => ['min' => 1, 'max' => 10],
			'2026-09-02' => ['min' => 11, 'max' => 50],
			'2026-09-03' => ['min' => 12, 'max' => 20],
		];

		$this->assertSame(['2026-09-01' => 10, '2026-09-03' => 20], backfillReadings($days, '2026-09-04'));
	}

	public function testNoReadingPassesTheFirstRecordedOne(): void {
		$days = [
			'2026-09-01' => ['min' => 1, 'max' => 10],
			'2026-09-02' => ['min' => 11, 'max' => 40],
		];

		$this->assertSame(['2026-09-01' => 10], backfillReadings($days, '2026-09-03', 30));
	}
}
