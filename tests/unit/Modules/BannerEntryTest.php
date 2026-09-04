<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\banner\bannerEntry;
use Kokonotsuba\Modules\banner\bannerPreset;
use Kokonotsuba\post\helper\postDateFormatter;

/**
 * Unit tests for the banner DTO row-builders.
 *
 * The real postDateFormatter pulls in the i18n layer, so we substitute a stub
 * that returns a fixed label — the entry's own URL/preset logic is what's under
 * test here, not date formatting (covered by its own tests elsewhere).
 */
final class BannerEntryTest extends TestCase {

	private function formatter(): postDateFormatter {
		return new class extends postDateFormatter {
			public function __construct() {}
			public function formatFromDateString(\DateTime|string $datetime): string {
				return 'FORMATTED';
			}
		};
	}

	protected function setUp(): void {
		requireModuleFile('banner/bannerPreset.php');
		requireModuleFile('banner/bannerPresetRegistry.php');
		requireModuleFile('banner/bannerEntry.php');
	}

	private function preset(bool $usesLink = true): bannerPreset {
		return new bannerPreset('ad', 'banner_preset_ad', 468, 60, 204800, true, 300, $usesLink);
	}

	private function make(): bannerEntry {
		$e = new bannerEntry();
		$e->id = 9;
		$e->link = 'https://example.com/promo';
		$e->banner_file_name = 'my banner.png';
		$e->ip_address = '203.0.113.5';
		$e->preset = 'ad';
		$e->is_active = 1;
		$e->is_approved = 0;
		$e->date_submitted = '2025-01-01 00:00:00';
		return $e;
	}

	public function testPublicRowBuildsEncodedImageUrl(): void {
		$row = $this->make()->toPublicTemplateRow('koko.php?mode=module&load=banner', $this->preset(), $this->formatter());
		// The filename is urlencoded (space → +) and the whole URL is then
		// HTML-escaped by sanitizeStr (& → &amp;).
		$this->assertStringContains('file=my+banner.png', $row['{$IMAGE_URL}']);
		$this->assertStringContains('&amp;file=', $row['{$IMAGE_URL}']);
		$this->assertSame('468', $row['{$BANNER_WIDTH}']);
		$this->assertSame('60', $row['{$BANNER_HEIGHT}']);
		$this->assertSame('FORMATTED', $row['{$DATE}']);
	}

	public function testRowDimensionsComeFromThePresetNotTheRow(): void {
		$row = $this->make()->toAdminTemplateRow('serve', new bannerPreset('board', 'banner_preset_board', 300, 100, 1, false, 0, false), $this->formatter());
		$this->assertSame('300', $row['{$BANNER_WIDTH}']);
		$this->assertSame('100', $row['{$BANNER_HEIGHT}']);
		$this->assertSame('', $row['{$USES_LINK}']);
	}

	public function testNullLinkBecomesHash(): void {
		$e = $this->make();
		$e->link = null;
		$row = $e->toPublicTemplateRow('serve', $this->preset(), $this->formatter());
		$this->assertSame('#', $row['{$LINK}']);
	}

	public function testAdminRowReflectsActiveAndApprovalFlags(): void {
		$row = $this->make()->toAdminTemplateRow('serve', $this->preset(), $this->formatter());
		$this->assertSame('Yes', $row['{$IS_ACTIVE}']);
		$this->assertSame('No', $row['{$IS_APPROVED}']);
		$this->assertSame('unapproved', $row['{$APPROVED_CLASS}']);
		$this->assertSame('9', $row['{$ID}']);
	}
}
