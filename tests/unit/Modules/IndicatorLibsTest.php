<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;

use function Kokonotsuba\Modules\autoSage\getAutoSageIndicator;
use function Kokonotsuba\Modules\lockThread\getLockIndicator;
use function Kokonotsuba\Modules\sticky\getStickyIndicator;

/**
 * Unit tests for the small thread-state indicator HTML helpers shared by the
 * autoSage, lockThread and sticky moderation modules.
 */
final class IndicatorLibsTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('autoSage/autoSageLibrary.php');
		requireModuleFile('lockThread/lockThreadLibrary.php');
		requireModuleFile('sticky/stickyLibrary.php');
	}

	public function testAutoSageIndicator(): void {
		$html = getAutoSageIndicator();
		$this->assertStringContains('class="autosage"', $html);
		$this->assertStringContains('AS', $html);
		$this->assertStringContains('cannot be bumped', $html);
	}

	public function testLockIndicatorUsesStaticUrl(): void {
		$html = getLockIndicator('https://static.example/');
		$this->assertStringContains('https://static.example/image/locked.png', $html);
		$this->assertStringContains('lockIcon', $html);
		$this->assertStringContains('<img', $html);
	}

	public function testStickyIndicatorUsesStaticUrl(): void {
		$html = getStickyIndicator('https://static.example/');
		$this->assertStringContains('https://static.example/image/sticky.png', $html);
		$this->assertStringContains('stickyIcon', $html);
		$this->assertStringContains('<img', $html);
	}
}
