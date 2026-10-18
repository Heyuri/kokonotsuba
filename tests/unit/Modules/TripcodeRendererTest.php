<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\tripcode\tripcodeRenderer;

/**
 * Unit tests for the tripcode module's HTML renderer.
 */
final class TripcodeRendererTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('tripcode/tripcode_src/tripcodeRenderer.php');
	}

	public function testPlainNameUnchangedWithoutTripOrCapcode(): void {
		$r = new tripcodeRenderer([], []);
		$this->assertSame('Anonymous', $r->renderTripcode('Anonymous', '', '', ''));
	}

	public function testRegularTripcodeRendersDiamond(): void {
		$r = new tripcodeRenderer([], []);
		$out = $r->renderTripcode('Bob', 'aBcDeF', '', '');
		$this->assertStringContains('postertrip', $out);
		$this->assertStringContains('◆aBcDeF', $out);
	}

	public function testSecureTripcodeRendersStar(): void {
		$r = new tripcodeRenderer([], []);
		$out = $r->renderTripcode('Bob', '', 'sEcUrE', '');
		$this->assertStringContains('★sEcUrE', $out);
	}

	public function testStaffCapcodeWrapsName(): void {
		$r = new tripcodeRenderer([], [
			'ADMINKEY' => ['capcodeHtml' => '%s ## Admin'],
		]);
		$out = $r->renderTripcode('Bob', '', '', 'ADMINKEY');
		$this->assertStringContains('postername', $out);
		$this->assertStringContains('Bob ## Admin', $out);
	}

	public function testUnknownStaffCapcodeDrawsNoWrapper(): void {
		$r = new tripcodeRenderer([], []);
		// A capcode nothing defines (a removed config key, a disabled module) must not become a
		// format string that swallows the name.
		$out = $r->renderTripcode('Bob', '', '', 'PLAIN');
		$this->assertSame('Bob', $out);
	}

	public function testStaffCapcodesMayArriveLazily(): void {
		$called = 0;
		$r = new tripcodeRenderer([], function () use (&$called): array {
			$called++;
			return ['threadOwner' => ['capcodeHtml' => '<span class="capcode capcodeThreadOwner">%s ## OP</span>']];
		});

		$this->assertStringContains('Bob ## OP', $r->renderTripcode('Bob', '', '', 'threadOwner'));
		$r->renderTripcode('Bob', '', '', 'threadOwner');
		$this->assertSame(1, $called);
	}

	public function testUserCapcodeAppliedForMatchingTripcode(): void {
		$userCapcodes = [
			['tripcode' => 'aBcDeF', 'is_secure' => 0, 'color_hex' => '#ff0000', 'cap_text' => 'VIP'],
		];
		$r = new tripcodeRenderer($userCapcodes, []);
		$out = $r->renderTripcode('Bob', 'aBcDeF', '', '');

		$this->assertStringContains('capcodeSection', $out);
		$this->assertStringContains('color:#ff0000', $out);
		$this->assertStringContains('## VIP', $out);
		$this->assertStringContains('◆aBcDeF', $out);
	}

	public function testUserCapcodeIgnoredWhenTripcodeDoesNotMatch(): void {
		$userCapcodes = [
			['tripcode' => 'someoneelse', 'is_secure' => 0, 'color_hex' => '#0f0', 'cap_text' => 'VIP'],
		];
		$r = new tripcodeRenderer($userCapcodes, []);
		$out = $r->renderTripcode('Bob', 'aBcDeF', '', '');
		$this->assertStringNotContains('capcodeSection', $out);
	}
}
