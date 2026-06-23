<?php

namespace Koko\Tests\Unit\Puchiko;

use Koko\Tests\Framework\TestCase;

use function Puchiko\strings\formatFileSize;
use function Puchiko\strings\containsHtmlTags;
use function Puchiko\strings\buildSmartQuery;
use function Puchiko\strings\truncateText;
use function Puchiko\strings\autoLink;
use function Puchiko\strings\extractGetParams;
use function Puchiko\strings\generateUid;
use function Puchiko\strings\strlenUnicode;

/**
 * Unit tests for the Puchiko\strings helpers.
 */
final class StringsTest extends TestCase {

	public function testFormatFileSizeBytes(): void {
		$this->assertSame('0 B', formatFileSize(0));
		$this->assertSame('512 B', formatFileSize(512));
		$this->assertSame('1023 B', formatFileSize(1023));
	}

	public function testFormatFileSizeKilobytes(): void {
		$this->assertSame('1 KB', formatFileSize(1024));
		// Sub-KB remainders are truncated, not rounded.
		$this->assertSame('1 KB', formatFileSize(1536));
		$this->assertSame('2 KB', formatFileSize(2048));
	}

	public function testFormatFileSizeMegabytes(): void {
		$this->assertSame('1 MB', formatFileSize(1024 * 1024));
		$this->assertSame('1.5 MB', formatFileSize(1572864));
	}

	public function testContainsHtmlTags(): void {
		$this->assertTrue(containsHtmlTags('<b>bold</b>'));
		$this->assertTrue(containsHtmlTags('hello <br> world'));
		$this->assertFalse(containsHtmlTags('just plain text'));
		$this->assertFalse(containsHtmlTags(''));
	}

	public function testBuildSmartQueryDropsDefaults(): void {
		// A value matching the default is omitted entirely.
		$this->assertSame(
			'koko.php?mode=admin',
			buildSmartQuery('koko.php?mode=admin', ['page' => '1'], ['page' => '1'])
		);
	}

	public function testBuildSmartQueryKeepsNonDefaults(): void {
		$this->assertSame(
			'koko.php?mode=admin&page=2',
			buildSmartQuery('koko.php?mode=admin', ['page' => '1'], ['page' => '2'])
		);
	}

	public function testBuildSmartQuerySkipsEmptyValues(): void {
		$this->assertSame(
			'koko.php?mode=admin',
			buildSmartQuery('koko.php?mode=admin', [], ['q' => '', 'page' => 0])
		);
	}

	public function testBuildSmartQueryUsesQuestionMarkWhenNotAppending(): void {
		$this->assertSame(
			'koko.php?page=2',
			buildSmartQuery('koko.php', ['page' => '1'], ['page' => '2'], false)
		);
	}

	public function testTruncateTextLeavesShortStrings(): void {
		$this->assertSame('hello', truncateText('hello', 10));
		$this->assertSame('hello', truncateText('hello', 5));
	}

	public function testTruncateTextAppendsEllipsis(): void {
		$result = truncateText('abcdefg', 5);
		$this->assertStringContains('abcd', $result);
		$this->assertStringContains('…', $result);
	}

	public function testTruncateTextDoesNotSplitMultibyte(): void {
		// Four emoji, truncate to 2 — must remain valid UTF-8 (no mojibake).
		$result = truncateText('😀🎌🔥👍', 2, 'UTF-8', false);
		$this->assertSame('😀🎌', $result);
	}

	public function testAutoLinkWrapsUrls(): void {
		$out = autoLink('visit http://example.com/page today');
		$this->assertStringContains('<a href="http://example.com/page"', $out);
		$this->assertStringContains('rel="nofollow noreferrer"', $out);
		$this->assertStringContains('target="_blank"', $out);
	}

	public function testAutoLinkLeavesPlainTextUntouched(): void {
		$this->assertSame('no links here', autoLink('no links here'));
	}

	public function testExtractGetParams(): void {
		$this->assertSame(['a' => '1', 'b' => '2'], extractGetParams('http://x.test/?a=1&b=2'));
		$this->assertSame([], extractGetParams('http://x.test/no-query'));
	}

	public function testStrlenUnicodeCountsCharactersNotBytes(): void {
		// "café" is 5 bytes but 4 characters.
		$this->assertSame(4, strlenUnicode('café'));
		$this->assertSame(1, strlenUnicode('😀'));
	}

	public function testGenerateUidLengthAndCharset(): void {
		$uid = generateUid(8);
		$this->assertSame(8, strlen($uid));
		$this->assertMatchesRegex('/^[0-9a-f]+$/', $uid);

		// Two consecutive UIDs should differ.
		$this->assertNotSame(generateUid(8), generateUid(8));
	}
}
