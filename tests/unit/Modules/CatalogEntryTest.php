<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\catalog\catalogEntry;

/**
 * Unit tests for the catalog DTO.
 */
final class CatalogEntryTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('catalog/catalogEntry.php');
	}

	private function make(bool $sticky = false, int $thumbWidth = 250): catalogEntry {
		return new catalogEntry(
			threadNumber: 123,
			threadUrl: 'res/123.html',
			thumbnailUrl: 'thumb/123s.jpg',
			thumbWidth: $thumbWidth,
			subject: 'Hello',
			comment: 'A comment',
			replyCount: 7,
			postInfoExtra: 'extra',
			isSticky: $sticky,
		);
	}

	public function testGetters(): void {
		$e = $this->make(true);
		$this->assertSame(123, $e->getThreadNumber());
		$this->assertSame('res/123.html', $e->getThreadUrl());
		$this->assertSame('thumb/123s.jpg', $e->getThumbnailUrl());
		$this->assertSame(250, $e->getThumbWidth());
		$this->assertSame('Hello', $e->getSubject());
		$this->assertSame('A comment', $e->getComment());
		$this->assertSame(7, $e->getReplyCount());
		$this->assertSame('extra', $e->getPostInfoExtra());
		$this->assertTrue($e->isSticky());
	}

	public function testToJson(): void {
		$json = $this->make()->toJson();
		$this->assertSame(123, $json['no']);
		$this->assertSame('res/123.html', $json['url']);
		$this->assertSame('thumb/123s.jpg', $json['thumb']);
		$this->assertSame(250, $json['tw']);
		$this->assertSame(7, $json['r']);
		$this->assertFalse($json['sticky']);
	}

	public function testToTemplateRowIncludesThumbWidthWhenPresent(): void {
		$row = $this->make(false, 200)->toTemplateRow('icon/replies.png');
		$this->assertStringContains('width="200"', $row['{$THUMB_HTML}']);
		$this->assertStringContains('thumb/123s.jpg', $row['{$THUMB_HTML}']);
		$this->assertSame('res/123.html', $row['{$THREAD_URL}']);
		$this->assertSame(7, $row['{$REPLY_COUNT}']);
	}

	public function testToTemplateRowOmitsWidthWhenZero(): void {
		$row = $this->make(false, 0)->toTemplateRow('icon/replies.png');
		$this->assertStringNotContains('width=', $row['{$THUMB_HTML}']);
	}
}
