<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\cache\thread_fragment\threadFragmentHitCounter;

/**
 * The per-request tally behind the thread cache statistics: off until started, one row per
 * board, thread and variant, and nothing counted while paused.
 */
final class ThreadFragmentHitCounterTest extends TestCase {
	private array $flushed = [];

	protected function tearDown(): void {
		threadFragmentHitCounter::reset();
		$this->flushed = [];
	}

	private function start(): void {
		threadFragmentHitCounter::start(function (array $rows): void { $this->flushed[] = $rows; }, false);
	}

	public function testNothingIsCountedBeforeItStarts(): void {
		threadFragmentHitCounter::note(1, 't1', 'index-5', true);
		$this->start();
		threadFragmentHitCounter::flush();

		$this->assertSame([], $this->flushed);
	}

	public function testRowsAreAggregatedAndSortedByKey(): void {
		$this->start();
		threadFragmentHitCounter::note(2, 't1', 'index-5', true);
		threadFragmentHitCounter::note(1, 't9', 'thread-1', false);
		threadFragmentHitCounter::note(2, 't1', 'index-5', true);
		threadFragmentHitCounter::note(2, 't1', 'index-5', false);
		threadFragmentHitCounter::flush();

		$this->assertSame([[
			['boardUid' => 1, 'threadUid' => 't9', 'variant' => 'thread-1', 'hits' => 0, 'misses' => 1],
			['boardUid' => 2, 'threadUid' => 't1', 'variant' => 'index-5', 'hits' => 2, 'misses' => 1],
		]], $this->flushed);
	}

	public function testPausedWorkIsNotCountedAndItsResultComesBack(): void {
		$this->start();
		$result = threadFragmentHitCounter::paused(function (): string {
			threadFragmentHitCounter::note(1, 't1', 'index-5', true);
			return 'drawn';
		});
		threadFragmentHitCounter::note(1, 't1', 'index-5', false);
		threadFragmentHitCounter::flush();

		$this->assertSame('drawn', $result);
		$this->assertSame(0, $this->flushed[0][0]['hits']);
		$this->assertSame(1, $this->flushed[0][0]['misses']);
	}

	public function testAFailingSinkLosesTheCountsQuietly(): void {
		threadFragmentHitCounter::start(function (): void { throw new \RuntimeException('no table'); }, false);
		threadFragmentHitCounter::note(1, 't1', 'index-5', true);
		threadFragmentHitCounter::flush();
		threadFragmentHitCounter::flush();

		$this->assertTrue(true);
	}

	public function testABoardlessCacheCountsNothing(): void {
		$this->start();
		threadFragmentHitCounter::note(0, 't1', 'index-5', true);
		threadFragmentHitCounter::flush();

		$this->assertSame([], $this->flushed);
	}
}
