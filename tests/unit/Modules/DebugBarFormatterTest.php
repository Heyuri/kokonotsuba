<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\debugBar\debugBarFormatter;

require_once KOKO_TEST_ROOT . '/module/debugBar/debugBarFormatter.php';

/** The numbers on the bar, as the reader sees them. */
final class DebugBarFormatterTest extends TestCase {

	private function snapshot(array $overrides = []): array {
		return $overrides + [
			'wallSeconds' => 0.2,
			'cpuSeconds' => 0.05,
			'cpuUserSeconds' => 0.04,
			'cpuSystemSeconds' => 0.01,
			'queryCount' => 12,
			'querySeconds' => 0.0345,
			'peakMemoryBytes' => 6 * 1048576,
		];
	}

	public function testEveryFieldIsFormatted(): void {
		$values = debugBarFormatter::format($this->snapshot());

		$this->assertSame('200.0 ms', $values['total']);
		$this->assertSame('50.0 ms (25%)', $values['cpu']);
		$this->assertSame('12 / 34.5 ms', $values['queries']);
		$this->assertSame('6.0 MB', $values['memory']);
	}

	public function testZeroWallTimeDoesNotDivide(): void {
		$values = debugBarFormatter::format($this->snapshot(['wallSeconds' => 0.0]));

		$this->assertSame('50.0 ms (0%)', $values['cpu']);
	}

	public function testMoreThanOneCoreReadsOverAHundredPercent(): void {
		$this->assertSame('150%', debugBarFormatter::percent(0.3, 0.2));
	}

	public function testSubMillisecondValuesKeepOneDecimal(): void {
		$this->assertSame('0.0 ms', debugBarFormatter::milliseconds(0.00004));
		$this->assertSame('0.1 ms', debugBarFormatter::milliseconds(0.00005));
		$this->assertSame('0.9 ms', debugBarFormatter::milliseconds(0.00094));
	}

	public function testLargeValuesAreGroupedForReading(): void {
		$this->assertSame('12,345.7 ms', debugBarFormatter::milliseconds(12.34567));
		$this->assertSame('1,024.0 MB', debugBarFormatter::megabytes(1024 * 1048576));
	}

	public function testAnIdleRequestIsAllZeros(): void {
		$values = debugBarFormatter::format($this->snapshot([
			'wallSeconds' => 0.0, 'cpuSeconds' => 0.0, 'queryCount' => 0, 'querySeconds' => 0.0, 'peakMemoryBytes' => 0,
		]));

		$this->assertSame(['total' => '0.0 ms', 'cpu' => '0.0 ms (0%)', 'queries' => '0 / 0.0 ms', 'memory' => '0.0 MB'], $values);
	}

	public function testATypicalThreadPage(): void {
		$values = debugBarFormatter::format($this->snapshot([
			'wallSeconds' => 0.0443, 'cpuSeconds' => 0.0170, 'queryCount' => 24, 'querySeconds' => 0.0274, 'peakMemoryBytes' => 4 * 1048576,
		]));

		$this->assertSame('44.3 ms', $values['total']);
		$this->assertSame('17.0 ms (38%)', $values['cpu']);
		$this->assertSame('24 / 27.4 ms', $values['queries']);
		$this->assertSame('4.0 MB', $values['memory']);
	}

	public function testNothingInTheOutputIsMarkup(): void {
		$values = debugBarFormatter::format($this->snapshot(['queryCount' => 3]));

		$this->assertFalse((bool)preg_match('/[<>&"]/', implode(' ', $values)));
	}
}
