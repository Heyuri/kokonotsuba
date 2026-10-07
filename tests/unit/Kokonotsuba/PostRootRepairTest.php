<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\postRootRepair;

/** Recovering post times an auto-updating root overwrote. */
final class PostRootRepairTest extends TestCase {

	public function testParsesTheMarkedUpForm(): void {
		$now = '<span class="postDate">2014/09/10</span><span class="postDay">(Wed)</span><span class="postTime">07:15:06</span>';
		$this->assertSame(gmmktime(7, 15, 6, 9, 10, 2014), postRootRepair::parseNow($now));
	}

	public function testParsesTwoDigitYearsAndIdSuffixes(): void {
		$this->assertSame(gmmktime(23, 5, 0, 1, 2, 2009), postRootRepair::parseNow('09/01/02(金)23:05 ID:abc123'));
	}

	public function testTextWithoutADateIsNull(): void {
		$this->assertNull(postRootRepair::parseNow('yesterday'));
		$this->assertNull(postRootRepair::parseNow('2020/02/30(Sun)10:00:00'));
	}

	public function testTheOffsetMostRowsAgreeOnWins(): void {
		$votes = [];
		postRootRepair::vote($votes, 1000 + 36000, 1000);
		postRootRepair::vote($votes, 5000 + 36000, 5000);
		postRootRepair::vote($votes, 9000 + 32400, 9000);

		$this->assertSame(36000, postRootRepair::winningOffset($votes));
	}

	public function testOverwrittenRowsDoNotVote(): void {
		$votes = [];
		postRootRepair::vote($votes, 1000 + 86400 * 30, 1000);

		$this->assertNull(postRootRepair::winningOffset($votes));
	}

	public function testOnlyRootsMovedLaterAreRepaired(): void {
		$posted = 1_700_000_000;

		$this->assertSame($posted - 3600, postRootRepair::repairedRoot($posted + 86400, $posted, -3600));
		$this->assertNull(postRootRepair::repairedRoot($posted - 3600, $posted, -3600));
		$this->assertNull(postRootRepair::repairedRoot($posted, $posted, -3600));
	}
}
