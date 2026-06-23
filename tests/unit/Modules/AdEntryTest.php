<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\ads\adEntry;

/**
 * Unit tests for the ads DTO row-builder.
 */
final class AdEntryTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('ads/adEntry.php');
	}

	private function make(string $type = 'image'): adEntry {
		$e = new adEntry();
		$e->id = 5;
		$e->slot = 'top';
		$e->type = $type;
		$e->src = 'https://cdn.example/ad.png';
		$e->href = 'https://example.com';
		$e->alt = 'Buy stuff';
		$e->html = '<b>x</b>';
		$e->enabled = 1;
		$e->date_added = '2024-01-15 10:30:00';
		return $e;
	}

	public function testImageTypeSelectsImageOption(): void {
		$row = $this->make('image')->toAdminTemplateRow();
		$this->assertSame('selected', $row['{$TYPE_IMAGE_SEL}']);
		$this->assertSame('', $row['{$TYPE_SCRIPT_SEL}']);
		$this->assertSame('5', $row['{$ID}']);
		$this->assertSame('1', $row['{$ENABLED}']);
	}

	public function testScriptTypeSelectsScriptOption(): void {
		$row = $this->make('script')->toAdminTemplateRow();
		$this->assertSame('', $row['{$TYPE_IMAGE_SEL}']);
		$this->assertSame('selected', $row['{$TYPE_SCRIPT_SEL}']);
	}

	public function testDateIsFormattedToYmd(): void {
		$row = $this->make()->toAdminTemplateRow();
		$this->assertSame('2024-01-15', $row['{$DATE}']);
	}

	public function testInvalidDateFallsBackToRawValue(): void {
		$e = $this->make();
		$e->date_added = 'not a date';
		$row = $e->toAdminTemplateRow();
		$this->assertSame('not a date', $row['{$DATE}']);
	}

	public function testHtmlIsEscaped(): void {
		$row = $this->make()->toAdminTemplateRow();
		$this->assertStringNotContains('<b>', $row['{$HTML}']);
	}

	public function testNullSrcRendersEmptyString(): void {
		$e = $this->make();
		$e->src = null;
		$row = $e->toAdminTemplateRow();
		$this->assertSame('', $row['{$SRC}']);
	}
}
