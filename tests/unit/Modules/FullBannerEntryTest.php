<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\fullBanner\fullBannerEntry;
use Kokonotsuba\post\helper\postDateFormatter;

/**
 * Unit tests for the fullBanner DTO row-builders.
 *
 * The real postDateFormatter pulls in the i18n layer, so we substitute a stub
 * that returns a fixed label — the entry's own URL/flag logic is what's under
 * test here, not date formatting (covered by its own tests elsewhere).
 */
final class FullBannerEntryTest extends TestCase {

	private function formatter(): postDateFormatter {
		return new class extends postDateFormatter {
			public function __construct() {}
			public function formatFromDateString(\DateTime|string $datetime): string {
				return 'FORMATTED';
			}
		};
	}

	protected function setUp(): void {
		requireModuleFile('fullBanner/fullBannerEntry.php');
	}

	private function make(): fullBannerEntry {
		$e = new fullBannerEntry();
		$e->id = 9;
		$e->link = 'https://example.com/promo';
		$e->banner_file_name = 'my banner.png';
		$e->ip_address = '203.0.113.5';
		$e->is_active = 1;
		$e->is_approved = 0;
		$e->date_submitted = '2025-01-01 00:00:00';
		return $e;
	}

	public function testPublicRowBuildsEncodedImageUrl(): void {
		$row = $this->make()->toPublicTemplateRow('koko.php?mode=module&load=fullBanner', 468, 60, $this->formatter());
		// The filename is urlencoded (space → +) and the whole URL is then
		// HTML-escaped by sanitizeStr (& → &amp;).
		$this->assertStringContains('file=my+banner.png', $row['{$IMAGE_URL}']);
		$this->assertStringContains('&amp;file=', $row['{$IMAGE_URL}']);
		$this->assertSame('468', $row['{$BANNER_WIDTH}']);
		$this->assertSame('60', $row['{$BANNER_HEIGHT}']);
		$this->assertSame('FORMATTED', $row['{$DATE}']);
	}

	public function testNullLinkBecomesHash(): void {
		$e = $this->make();
		$e->link = null;
		$row = $e->toPublicTemplateRow('serve', 1, 1, $this->formatter());
		$this->assertSame('#', $row['{$LINK}']);
	}

	public function testAdminRowReflectsActiveAndApprovalFlags(): void {
		$row = $this->make()->toAdminTemplateRow('serve', 468, 60, $this->formatter());
		$this->assertSame('Yes', $row['{$IS_ACTIVE}']);
		$this->assertSame('No', $row['{$IS_APPROVED}']);
		$this->assertSame('unapproved', $row['{$APPROVED_CLASS}']);
		$this->assertSame('9', $row['{$ID}']);
	}
}
