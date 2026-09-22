<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\textFormat;
use Kokonotsuba\quote_link\textQuoteMatcher;

/**
 * The rules a text quote is matched by. static/js/quoteLookup.js applies the same ones to the
 * posts on a page, and tests/js/ pins that side; a change here needs the matching change there.
 */
final class TextQuoteMatcherTest extends TestCase {

	private function matches(string $comment, string $needle, bool $quoted = false, textFormat $format = textFormat::PLAIN_TEXT): bool {
		return textQuoteMatcher::commentMatches($comment, $format, $needle, $quoted);
	}

	// ─── Needles ──────────────────────────────────────────────────

	public function testNeedleIsTrimmed(): void {
		$this->assertSame('hello', textQuoteMatcher::normalizeNeedle("  hello\t "));
		$this->assertSame('草', textQuoteMatcher::normalizeNeedle("\u{3000}草\u{A0}"));
	}

	public function testEmptyNeedleIsRefused(): void {
		$this->assertNull(textQuoteMatcher::normalizeNeedle(''));
		$this->assertNull(textQuoteMatcher::normalizeNeedle(" \u{3000}\t"));
	}

	public function testOverlongNeedleIsRefused(): void {
		$max = textQuoteMatcher::MAX_NEEDLE_LENGTH;
		$this->assertNotNull(textQuoteMatcher::normalizeNeedle(str_repeat('あ', $max)));
		$this->assertNull(textQuoteMatcher::normalizeNeedle(str_repeat('あ', $max + 1)));
	}

	public function testBrokenUtf8AndControlCharactersAreRefused(): void {
		$this->assertNull(textQuoteMatcher::normalizeNeedle("abc\xFF"));
		$this->assertNull(textQuoteMatcher::normalizeNeedle("two\nlines"));
		$this->assertNull(textQuoteMatcher::normalizeNeedle("nul\x00byte"));
		$this->assertNotNull(textQuoteMatcher::normalizeNeedle("tab\tinside"));
	}

	public function testPostNumbers(): void {
		$this->assertSame(123, textQuoteMatcher::postNumber('123'));
		$this->assertSame(123, textQuoteMatcher::postNumber('No.123'));
		$this->assertSame(123, textQuoteMatcher::postNumber('No. 123'));
		$this->assertSame(7, textQuoteMatcher::postNumber('007'));
		$this->assertNull(textQuoteMatcher::postNumber('123 lol'));
		$this->assertNull(textQuoteMatcher::postNumber('no.123'));
	}

	public function testImpossibleNumberStaysANumber(): void {
		// so it is answered "not found" rather than searched for as text
		$this->assertSame(0, textQuoteMatcher::postNumber('0'));
		$this->assertSame(0, textQuoteMatcher::postNumber('99999999999999999999'));
	}

	public function testFileNameSplitting(): void {
		$this->assertSame(['cat', 'jpg'], textQuoteMatcher::splitFileName('cat.jpg'));
		$this->assertSame(['a.b c', 'webm'], textQuoteMatcher::splitFileName('a.b c.webm'));
		$this->assertNull(textQuoteMatcher::splitFileName('no extension'));
		$this->assertNull(textQuoteMatcher::splitFileName('.htaccess'));
		$this->assertNull(textQuoteMatcher::splitFileName('ends with dot.'));
		$this->assertNull(textQuoteMatcher::splitFileName('sentence. Another one'));
	}

	public function testDisplayedFileNameToleratesAStoredDot(): void {
		$this->assertSame('cat.jpg', textQuoteMatcher::displayedFileName('cat', 'jpg'));
		$this->assertSame('cat.jpg', textQuoteMatcher::displayedFileName('cat', '.jpg'));
	}

	public function testLikePatternEscapesWildcards(): void {
		$this->assertSame('%100\\% \\_sure\\_ \\\\o/%', textQuoteMatcher::likePattern('100% _sure_ \\o/'));
	}

	// ─── Comments ─────────────────────────────────────────────────

	public function testPlainQuoteMatchesOwnText(): void {
		$this->assertTrue($this->matches("first line\nthe cat sat", 'cat sat'));
		$this->assertFalse($this->matches('the cat sat', 'dog'));
	}

	public function testMatchingIsCaseSensitiveLikeThePageScript(): void {
		$this->assertFalse($this->matches('The Cat', 'the cat'));
	}

	public function testAPostRepeatingTheQuoteIsNotItsSource(): void {
		$this->assertFalse($this->matches(">the cat sat\nlol", 'the cat sat'));
		$this->assertFalse($this->matches("  ＞the cat sat", 'the cat sat'));
	}

	public function testQuoteOfAQuoteNeedsAQuotingPost(): void {
		$this->assertTrue($this->matches(">the cat sat\nlol", 'the cat sat', true));
		$this->assertFalse($this->matches('the cat sat', 'the cat sat', true));
	}

	public function testQuoteOfAQuoteAlsoSeesOwnTextOfAQuotingPost(): void {
		$this->assertTrue($this->matches(">something\nmy reply", 'my reply', true));
	}

	public function testEmptyNeedleMatchesNothing(): void {
		$this->assertFalse($this->matches('anything', ''));
	}

	public function testLegacyHtmlIsReadAsItsText(): void {
		$stored = 'Tom &amp; Jerry<br />&gt;implying<br>a &lt;b&gt; tag';

		$this->assertTrue($this->matches($stored, 'Tom & Jerry', false, textFormat::LEGACY_HTML));
		$this->assertTrue($this->matches($stored, 'a <b> tag', false, textFormat::LEGACY_HTML));
		$this->assertFalse($this->matches($stored, 'implying', false, textFormat::LEGACY_HTML));
		$this->assertTrue($this->matches($stored, 'implying', true, textFormat::LEGACY_HTML));
	}

	public function testLegacyMarkupIsNotSearchable(): void {
		$stored = '<span class="unkfunc">&gt;quoted</span><br><b>bold</b>';

		$this->assertFalse($this->matches($stored, 'span', false, textFormat::LEGACY_HTML));
		$this->assertTrue($this->matches($stored, 'bold', false, textFormat::LEGACY_HTML));
		$this->assertFalse($this->matches($stored, 'quoted', false, textFormat::LEGACY_HTML));
	}

	public function testPlainTextIsNotDecoded(): void {
		$this->assertTrue($this->matches('a &amp; b', '&amp;'));
		$this->assertFalse($this->matches('a &amp; b', 'a & b'));
	}

	public function testHasQuoteLine(): void {
		$this->assertTrue(textQuoteMatcher::hasQuoteLine("a\r\n>b", textFormat::PLAIN_TEXT));
		$this->assertFalse(textQuoteMatcher::hasQuoteLine('a > b', textFormat::PLAIN_TEXT));
	}
}
