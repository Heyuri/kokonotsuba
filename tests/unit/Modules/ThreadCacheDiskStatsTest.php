<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\threadCache\threadCacheDiskStats;

/** The figures the thread cache page shows for what is on disk. */
final class ThreadCacheDiskStatsTest extends TestCase {
	protected function setUp(): void {
		requireModuleFile('threadCache/threadCacheDiskStats.php');
	}

	public function testEntriesAreSummedPerKindAndThread(): void {
		$stats = threadCacheDiskStats::fromEntries([
			['threadUid' => 't1', 'variant' => 'index-5', 'size' => 100, 'mtime' => 30],
			['threadUid' => 't1', 'variant' => 'thread-1', 'size' => 200, 'mtime' => 10],
			['threadUid' => 't2', 'variant' => 'overboard-kokoimg-5', 'size' => 50, 'mtime' => 20],
			['threadUid' => 't2', 'variant' => sha1('odd'), 'size' => 1, 'mtime' => 40],
		]);

		$this->assertSame(4, $stats->files);
		$this->assertSame(351, $stats->bytes);
		$this->assertSame(2, $stats->threadCount());
		$this->assertSame(['index' => 1, 'thread' => 1, 'overboard' => 1, 'other' => 1], $stats->kinds);
		$this->assertSame(10, $stats->oldest);
		$this->assertSame(40, $stats->newest);
	}

	public function testAnEmptyBoardHasNoDates(): void {
		$stats = threadCacheDiskStats::fromEntries([]);

		$this->assertSame(0, $stats->files);
		$this->assertNull($stats->oldest);
	}

	/** Thread uids repeat across boards, so the total counts each board's separately. */
	public function testTheSumKeepsBoardsApart(): void {
		$a = threadCacheDiskStats::fromEntries([['threadUid' => 't1', 'variant' => 'index-5', 'size' => 1, 'mtime' => 5]]);
		$b = threadCacheDiskStats::fromEntries([['threadUid' => 't1', 'variant' => 'thread-1', 'size' => 2, 'mtime' => 9]]);
		$empty = threadCacheDiskStats::fromEntries([]);

		$sum = threadCacheDiskStats::sum([1 => $a, 2 => $b, 3 => $empty]);

		$this->assertSame(2, $sum->files);
		$this->assertSame(3, $sum->bytes);
		$this->assertSame(2, $sum->threadCount());
		$this->assertSame(5, $sum->oldest);
		$this->assertSame(9, $sum->newest);
	}
}
