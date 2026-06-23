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

	public function testUnknownStaffCapcodeReturnedRaw(): void {
		$r = new tripcodeRenderer([], []);
		// capcode not in staffCapcodes → wrapper returns the capcode itself,
		// which sprintf then applies to the name as a format string.
		$out = $r->renderTripcode('Bob', '', '', 'PLAIN');
		$this->assertSame('PLAIN', $out);
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
