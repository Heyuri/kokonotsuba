<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\commentMarker;
use Kokonotsuba\post\textFormat;
use Kokonotsuba\renderers\commentFormatter;

/**
 * The stored-text to HTML step.
 *
 * This is the only thing standing between a poster's text and the page, so most of what is
 * pinned here is that markup a poster types comes back out as text. The legacy path is pinned
 * just as hard in the other direction: those rows are already HTML and must not be touched.
 */
final class CommentFormatterTest extends TestCase {

	private const CONFIG = [
		'AUTO_LINK' => true,
		'REF_URL' => '',
		'FORTUNES' => ['Great luck', 'Small luck', 'Bad luck'],
	];

	private function formatter(array $overrides = []): commentFormatter {
		return new commentFormatter($overrides + self::CONFIG);
	}

	private function plain(string $comment, array $overrides = []): string {
		return $this->formatter($overrides)->commentToHtml($comment, textFormat::PLAIN_TEXT);
	}

	// ─── Escaping ─────────────────────────────────────────────────

	public function testTagsTypedByAPosterComeBackAsText(): void {
		$this->assertSame('&lt;b&gt;bold&lt;/b&gt;', $this->plain('<b>bold</b>'));
	}

	public function testScriptTagsAreNeutralized(): void {
		$html = $this->plain('<script>alert(1)</script>');

		$this->assertStringNotContains('<script', $html);
		$this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
	}

	public function testQuotesAreEscapedSoACommentCannotBreakOutOfAnAttribute(): void {
		$html = $this->plain('" onmouseover="alert(1)');

		$this->assertStringNotContains('"', $html);
	}

	public function testSingleQuotesAreEscapedToo(): void {
		$this->assertStringNotContains("'", $this->plain("it's"));
	}

	public function testAmpersandsAreEscapedExactlyOnce(): void {
		$this->assertSame('a &amp; b', $this->plain('a & b'));
	}

	public function testAnEntityTypedByAPosterIsShownLiterally(): void {
		// The poster typed the five characters "&amp;", so that is what they should read back.
		$this->assertSame('&amp;amp;', $this->plain('&amp;'));
	}

	// ─── Line breaks ──────────────────────────────────────────────

	public function testNewlinesBecomeBreakTags(): void {
		$this->assertSame('one<br>two', $this->plain("one\ntwo"));
	}

	public function testBlankLinesAreKept(): void {
		$this->assertSame('a<br><br>b', $this->plain("a\n\nb"));
	}

	public function testNoRawNewlineSurvivesIntoTheMarkup(): void {
		$this->assertStringNotContains("\n", $this->plain("a\nb\nc"));
	}

	// ─── Autolinking ──────────────────────────────────────────────

	public function testUrlsBecomeLinks(): void {
		$html = $this->plain('see https://example.com/x');

		$this->assertStringContains('<a href="https://example.com/x"', $html);
		$this->assertStringContains('rel="nofollow noreferrer"', $html);
	}

	public function testAutoLinkCanBeTurnedOff(): void {
		$html = $this->plain('see https://example.com', ['AUTO_LINK' => false]);

		$this->assertStringNotContains('<a href', $html);
	}

	public function testALinkAtTheEndOfALineDoesNotSwallowTheLineBreak(): void {
		$html = $this->plain("https://example.com\nnext line");

		$this->assertStringContains('</a><br>next line', $html);
	}

	public function testAUrlCannotSmuggleMarkupThroughTheAnchor(): void {
		$html = $this->plain('https://example.com/"><script>alert(1)</script>');

		$this->assertStringNotContains('<script', $html);
	}

	public function testRefUrlPrefixesTheHref(): void {
		$html = $this->plain('https://example.com', ['REF_URL' => 'https://jump.example/?']);

		$this->assertStringContains('href="https://jump.example/?https://example.com"', $html);
	}

	// ─── Quote markers ────────────────────────────────────────────

	public function testQuoteMarkersReachTheQuoteRendererEscaped(): void {
		// generateQuoteLinkHtml matches &gt;&gt;, so this is the spelling it has to be handed.
		$this->assertSame('&gt;&gt;123', $this->plain('>>123'));
	}

	// ─── Markers ──────────────────────────────────────────────────

	public function testFortuneMarkerBecomesTheFortuneLine(): void {
		$html = $this->plain('rolling' . commentMarker::make('fortune', '1'));

		$this->assertStringContains('class="fortune"', $html);
		$this->assertStringContains('Your fortune: Small luck', $html);
	}

	public function testTheSameFortuneAlwaysGetsTheSameColour(): void {
		$marker = commentMarker::make('fortune', '2');

		$this->assertSame($this->plain($marker), $this->plain($marker));
	}

	public function testAFortuneIndexThatNoLongerExistsRendersAsNothing(): void {
		$this->assertSame('x', $this->plain('x' . commentMarker::make('fortune', '99')));
	}

	public function testFortuneTextFromConfigIsEscaped(): void {
		$html = $this->formatter(['FORTUNES' => ['<b>lucky</b>']])
			->commentToHtml(commentMarker::make('fortune', '0'), textFormat::PLAIN_TEXT);

		$this->assertStringNotContains('<b>', $html);
	}

	public function testAMarkerLeftInADiceModuleFormatIsNotTouchedHere(): void {
		// The dice module expands its own markers from PostComment; this must leave them be.
		$html = $this->plain(commentMarker::make('dice', '2d6:4,5'));

		$this->assertStringContains('[[koko:dice:2d6:4,5]]', $html);
	}

	// ─── Legacy rows ──────────────────────────────────────────────

	public function testLegacyCommentsAreEmittedByteForByte(): void {
		$stored = '&gt;&gt;1<br>with <a href="https://example.com">a link</a> &amp; entities';

		$this->assertSame($stored, $this->formatter()->commentToHtml($stored, textFormat::LEGACY_HTML));
	}

	public function testLegacyCommentsAreNotAutolinkedAgain(): void {
		$stored = '<a href="https://example.com">https://example.com</a>';

		$this->assertSame($stored, $this->formatter()->commentToHtml($stored, textFormat::LEGACY_HTML));
	}

	public function testRawHtmlCommentsAreEmittedAsWritten(): void {
		$stored = '<marquee>staff nonsense</marquee>';

		$this->assertSame($stored, $this->formatter()->commentToHtml($stored, textFormat::RAW_HTML));
	}

	// ─── Fields ───────────────────────────────────────────────────

	public function testPlainFieldsAreEscaped(): void {
		$this->assertSame(
			'&lt;script&gt;alert(1)&lt;/script&gt;',
			commentFormatter::fieldToHtml('<script>alert(1)</script>', textFormat::PLAIN_TEXT)
		);
	}

	public function testAFieldOnARawHtmlPostIsStillEscaped(): void {
		// The raw-HTML tool exempts the comment only.
		$this->assertStringNotContains(
			'<script',
			commentFormatter::fieldToHtml('<script>alert(1)</script>', textFormat::RAW_HTML)
		);
	}

	public function testLegacyFieldsAreLeftAlone(): void {
		$this->assertSame(
			'&lt;b&gt;name&lt;/b&gt;',
			commentFormatter::fieldToHtml('&lt;b&gt;name&lt;/b&gt;', textFormat::LEGACY_HTML)
		);
	}

	public function testFieldsDoNotGainBreakTags(): void {
		$this->assertStringNotContains('<br', commentFormatter::fieldToHtml("a\nb", textFormat::PLAIN_TEXT));
	}

	// ─── Plain-text extraction ────────────────────────────────────

	public function testPlainTextOfAPlainCommentIsTheCommentWithoutMarkers(): void {
		$this->assertSame(
			'rolling ',
			commentFormatter::commentToPlainText('rolling ' . commentMarker::make('dice', '2d6:4,5'), textFormat::PLAIN_TEXT)
		);
	}

	public function testPlainTextOfALegacyCommentDropsMarkupAndEntities(): void {
		$this->assertSame(
			'>>1 hello & goodbye',
			commentFormatter::commentToPlainText('&gt;&gt;1<br>hello &amp; goodbye', textFormat::LEGACY_HTML)
		);
	}

	public function testPlainTextDoesNotRunWordsTogetherAcrossALineBreak(): void {
		$this->assertSame('one two', commentFormatter::commentToPlainText('one<br>two', textFormat::LEGACY_HTML));
	}

	public function testPlainTextOfALegacyFieldIsDecoded(): void {
		$this->assertSame(
			'a & b',
			commentFormatter::fieldToPlainText('a &amp; b', textFormat::LEGACY_HTML)
		);
	}

	public function testPlainTextOfAPlainFieldIsUnchanged(): void {
		$this->assertSame(
			'a &amp; b',
			commentFormatter::fieldToPlainText('a &amp; b', textFormat::PLAIN_TEXT)
		);
	}

	// ─── Empty input ──────────────────────────────────────────────

	public function testEmptyCommentsStayEmpty(): void {
		$this->assertSame('', $this->plain(''));
		$this->assertSame('', $this->formatter()->commentToHtml('', textFormat::LEGACY_HTML));
	}
}
