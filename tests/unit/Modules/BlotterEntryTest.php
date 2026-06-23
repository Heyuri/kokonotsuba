<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\blotter\blotterEntry;

/**
 * Unit tests for the blotter DTO row-builders.
 */
final class BlotterEntryTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('blotter/blotterEntry.php');
	}

	private function make(): blotterEntry {
		$e = new blotterEntry();
		$e->id = 42;
		$e->blotter_content = 'Server maintenance <tonight>';
		$e->added_by = 3;
		$e->added_by_username = 'mod_alice';
		$e->date_added = '2025-03-09 12:00:00';
		return $e;
	}

	public function testAdminRowFormatsDateAndEscapes(): void {
		$row = $this->make()->toAdminTemplateRow();
		$this->assertSame('2025-03-09', $row['{$DATE}']);
		$this->assertSame('mod_alice', $row['{$ADDED_BY}']);
		$this->assertSame('42', $row['{$UID}']);
		// Admin view escapes the comment.
		$this->assertStringNotContains('<tonight>', $row['{$COMMENT}']);
	}

	public function testPublicRowKeepsRawComment(): void {
		$row = $this->make()->toPublicTemplateRow();
		$this->assertSame('2025-03-09', $row['{$DATE}']);
		// Public view passes the stored content through verbatim.
		$this->assertSame('Server maintenance <tonight>', $row['{$COMMENT}']);
	}

	public function testNullUsernameRendersEmpty(): void {
		$e = $this->make();
		$e->added_by_username = null;
		$row = $e->toAdminTemplateRow();
		$this->assertSame('', $row['{$ADDED_BY}']);
	}

	public function testInvalidDateFallsBackToRaw(): void {
		$e = $this->make();
		$e->date_added = 'garbage';
		$row = $e->toAdminTemplateRow();
		$this->assertSame('garbage', $row['{$DATE}']);
	}
}
