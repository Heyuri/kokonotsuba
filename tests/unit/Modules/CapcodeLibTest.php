<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\error\BoardException;

use function Kokonotsuba\Modules\tripcode\validateCapcodeId;

/**
 * Unit tests for the capcode admin helper validateCapcodeId().
 */
final class CapcodeLibTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('tripcode/capcode_src/capcodeLib.php');
	}

	public function testAcceptsPositiveId(): void {
		validateCapcodeId(1);
		validateCapcodeId(9999);
		$this->pass(); // reached here without throwing
	}

	public function testRejectsZero(): void {
		$this->assertThrows(fn() => validateCapcodeId(0), BoardException::class);
	}

	public function testRejectsNegative(): void {
		$this->assertThrows(fn() => validateCapcodeId(-42), BoardException::class);
	}
}
