<?php

namespace Koko\Tests\Unit\Kokonotsuba\Renderers;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\renderers\post\postWarnings;
use Kokonotsuba\thread\Thread;

/** When a thread counts as too old to bump. */
final class PostWarningsTest extends TestCase {

	private const NOW = 1_700_000_000;

	private function thread(int $ageSeconds): Thread {
		return new Thread(['thread_created_time' => date('Y-m-d H:i:s', self::NOW - $ageSeconds)]);
	}

	private function warnings(int $maxAgeHours): postWarnings {
		return new postWarnings(['MAX_AGE_TIME' => $maxAgeHours], self::NOW);
	}

	public function testThreadPastTheLimitIsOld(): void {
		$this->assertTrue($this->warnings(24)->isOldThread($this->thread(25 * 3600), true));
	}

	public function testThreadWithinTheLimitIsNot(): void {
		$this->assertFalse($this->warnings(24)->isOldThread($this->thread(23 * 3600), true));
	}

	public function testNoLimitMeansNeverOld(): void {
		$this->assertFalse($this->warnings(0)->isOldThread($this->thread(10 * 365 * 24 * 3600), true));
	}

	public function testOnlyTheOpCarriesTheNotice(): void {
		$this->assertFalse($this->warnings(24)->isOldThread($this->thread(48 * 3600), false));
	}

	public function testNoThreadRowMeansNoNotice(): void {
		$this->assertFalse($this->warnings(24)->isOldThread(null, true));
	}
}
