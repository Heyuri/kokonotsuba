<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\debug\requestMetrics;

/** The counters behind the debug bar: wall time from the request start, statements summed, and their text kept only while profiling. */
final class RequestMetricsTest extends TestCase {

	protected function tearDown(): void {
		requestMetrics::keepStatements(false);
	}

	public function testQueriesAreCountedAndSummed(): void {
		requestMetrics::begin(microtime(true));
		requestMetrics::recordQuery(0.010);
		requestMetrics::recordQuery(0.005);

		$snapshot = requestMetrics::snapshot();

		$this->assertSame(2, $snapshot['queryCount']);
		$this->assertTrue(abs($snapshot['querySeconds'] - 0.015) < 1e-9);
	}

	public function testBeginResetsTheCounters(): void {
		requestMetrics::begin(microtime(true));
		requestMetrics::keepStatements(true);
		requestMetrics::recordQuery(1.0, 'SELECT 1');
		requestMetrics::begin(microtime(true));

		$snapshot = requestMetrics::snapshot();

		$this->assertSame(0, $snapshot['queryCount']);
		$this->assertSame(0.0, $snapshot['querySeconds']);
		$this->assertSame([], requestMetrics::statements());
	}

	public function testWallTimeIsMeasuredFromTheRequestStart(): void {
		requestMetrics::begin(100.0);

		$snapshot = requestMetrics::snapshot(100.25);

		$this->assertTrue(abs($snapshot['wallSeconds'] - 0.25) < 1e-9);
		$this->assertTrue($snapshot['cpuSeconds'] >= 0.0);
		$this->assertTrue($snapshot['peakMemoryBytes'] > 0);
	}

	public function testAClockSetBackNeverGoesNegative(): void {
		requestMetrics::begin(200.0);

		$this->assertSame(0.0, requestMetrics::snapshot(199.0)['wallSeconds']);
	}

	public function testStatementTextIsDroppedUnlessKept(): void {
		requestMetrics::begin(0.0);
		requestMetrics::recordQuery(0.001, 'SELECT 1');

		$this->assertSame([], requestMetrics::statements());
		$this->assertSame(1, requestMetrics::snapshot()['queryCount']);
	}

	public function testStatementsAreKeptInOrderWithTheirTimes(): void {
		requestMetrics::begin(0.0);
		requestMetrics::keepStatements(true);
		requestMetrics::recordQuery(0.002, "SELECT *\n\tFROM posts\n  WHERE post_uid = :uid");
		requestMetrics::recordQuery(0.003, 'UPDATE threads SET last_bump_time = ? WHERE thread_uid = ?');

		$this->assertSame([
			['sql' => 'SELECT * FROM posts WHERE post_uid = :uid', 'seconds' => 0.002],
			['sql' => 'UPDATE threads SET last_bump_time = ? WHERE thread_uid = ?', 'seconds' => 0.003],
		], requestMetrics::statements());
	}

	public function testTurningKeepingOffForgetsWhatWasKept(): void {
		requestMetrics::begin(0.0);
		requestMetrics::keepStatements(true);
		requestMetrics::recordQuery(0.001, 'SELECT 1');
		requestMetrics::keepStatements(false);
		requestMetrics::recordQuery(0.001, 'SELECT 2');

		$this->assertSame([], requestMetrics::statements());
		$this->assertSame(2, requestMetrics::snapshot()['queryCount']);
	}

	public function testTheListIsCappedButTheCountIsNot(): void {
		requestMetrics::begin(0.0);
		requestMetrics::keepStatements(true);
		for ($i = 0; $i < requestMetrics::STATEMENT_LIMIT + 50; $i++) {
			requestMetrics::recordQuery(0.0001, 'SELECT ' . $i);
		}

		$this->assertCount(requestMetrics::STATEMENT_LIMIT, requestMetrics::statements());
		$this->assertSame(requestMetrics::STATEMENT_LIMIT + 50, requestMetrics::snapshot()['queryCount']);
	}

	public function testLongStatementTextIsTruncated(): void {
		$compact = requestMetrics::compactSql('SELECT ' . str_repeat('a', 5000));

		$this->assertSame(requestMetrics::STATEMENT_TEXT_LIMIT + 3, strlen($compact));
		$this->assertTrue(str_ends_with($compact, '...'));
	}

	public function testANullStatementIsCountedButNotListed(): void {
		requestMetrics::begin(0.0);
		requestMetrics::keepStatements(true);
		requestMetrics::recordQuery(0.001, null);

		$this->assertSame([], requestMetrics::statements());
		$this->assertSame(1, requestMetrics::snapshot()['queryCount']);
	}
}
