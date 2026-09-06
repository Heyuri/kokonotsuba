<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\legacyTextConverter;
use Kokonotsuba\post\textFormat;
use Kokonotsuba\renderers\commentFormatter;

/**
 * Unwinding a comment stored as HTML back into the text a poster typed.
 *
 * The property that matters is the round trip: converting a stored comment and then formatting
 * the result should land back on the same page. Where it cannot - a fortune whose text is no
 * longer in the config - the post still has to read the same.
 */
final class LegacyTextConverterTest extends TestCase {

	private const FORTUNES = ['Great luck', 'Small luck', 'Bad luck'];

	private function reformat(string $text, array $config = []): string {
		$formatter = new commentFormatter($config + [
			'AUTO_LINK' => true,
			'REF_URL' => '',
			'FORTUNES' => self::FORTUNES,
		]);

		return $formatter->commentToHtml($text, textFormat::PLAIN_TEXT);
	}

	// ─── The basics ───────────────────────────────────────────────

	public function testBreakTagsBecomeNewlines(): void {
		$this->assertSame("one\ntwo", legacyTextConverter::comment('one<br>two'));
	}

	public function testEveryBreakSpellingBecomesANewline(): void {
		$this->assertSame("a\nb\nc", legacyTextConverter::comment('a<br>b<br />c'));
	}

	public function testEntitiesAreDecodedBackToCharacters(): void {
		$this->assertSame('a & b', legacyTextConverter::comment('a &amp; b'));
	}

	public function testQuoteMarkersComeBackAsAngleBrackets(): void {
		$this->assertSame('>>123', legacyTextConverter::comment('&gt;&gt;123'));
	}

	public function testMarkupATypedPosterEscapedComesBackAsText(): void {
		$this->assertSame('<b>bold</b>', legacyTextConverter::comment('&lt;b&gt;bold&lt;/b&gt;'));
	}

	public function testAutolinkAnchorsCollapseToTheirUrl(): void {
		$stored = 'see <a href="https://example.com/x" rel="nofollow noreferrer" target="_blank">https://example.com/x</a>';

		$this->assertSame('see https://example.com/x', legacyTextConverter::comment($stored));
	}

	public function testQuoteLinkAnchorsCollapseToTheirMarker(): void {
		$stored = '<a href="/koko.php?res=1" class="quotelink">&gt;&gt;1</a> hello';

		$this->assertSame('>>1 hello', legacyTextConverter::comment($stored));
	}

	public function testGreentextSpansCollapseToTheirText(): void {
		$stored = '<span class="unkfunc">&gt;implying</span>';

		$this->assertSame('>implying', legacyTextConverter::comment($stored));
	}

	public function testWordFilterSpansCollapseToTheirText(): void {
		$stored = 'oh <span style="background-color: rgb(1, 2, 3); color: rgb(4, 5, 6);">VAGINA</span>';

		$this->assertSame('oh VAGINA', legacyTextConverter::comment($stored));
	}

	// ─── Round trips ──────────────────────────────────────────────

	public function testAPlainCommentSurvivesTheRoundTrip(): void {
		$stored = 'hello<br>world &amp; friends';

		$this->assertSame($stored, $this->reformat(legacyTextConverter::comment($stored)));
	}

	public function testAnAutolinkedCommentIsRelinkedIdentically(): void {
		$stored = 'see <a href="https://example.com/x" rel="nofollow noreferrer" target="_blank">https://example.com/x</a>';

		$this->assertSame($stored, $this->reformat(legacyTextConverter::comment($stored)));
	}

	public function testConvertingIsIdempotent(): void {
		$once = legacyTextConverter::comment('a<br>b &amp; c');

		$this->assertSame($once, legacyTextConverter::comment($once));
	}

	// ─── Dice ─────────────────────────────────────────────────────

	public function testAnEmailRollBecomesAMarker(): void {
		$stored = 'rolling
			<div class="rollContainer">
				<p class="roll" title="This is a dice roll">[NUMBERS: 4, 5]</p>
			</div>';

		$this->assertSame('rolling[[koko:diceemail:4,5]]', legacyTextConverter::comment($stored));
	}

	public function testASingleEmailRollBecomesAMarker(): void {
		$stored = '<div class="rollContainer"><p class="roll" title="This is a dice roll">[NUMBER: 3]</p></div>';

		$this->assertSame('[[koko:diceemail:3]]', legacyTextConverter::comment($stored));
	}

	public function testACommentRollKeepsItsNotationAndValues(): void {
		$stored = '<span class="rollContainer">dice2d6=<span class="roll" title="This is a dice roll">4, 5 (9)</span></span>';

		$this->assertSame("\n[[koko:dice:2d6:4,5]]", legacyTextConverter::comment($stored));
	}

	public function testACommentRollKeepsItsModifier(): void {
		$stored = '<span class="rollContainer">dice2d6+1=<span class="roll">4, 5 (10)</span></span>';

		$this->assertSame("\n[[koko:dice:2d6+1:4,5]]", legacyTextConverter::comment($stored));
	}

	public function testASingleDieRollWithNoModifierHasNoTotalToParse(): void {
		$stored = '<span class="rollContainer">dice1d6=<span class="roll">4</span></span>';

		$this->assertSame("\n[[koko:dice:1d6:4]]", legacyTextConverter::comment($stored));
	}

	/** The whole point of the marker: the numbers a poster rolled never change. */
	public function testARollKeepsItsNumbersThroughTheRoundTrip(): void {
		$stored = '<span class="rollContainer">dice2d6=<span class="roll">4, 5 (9)</span></span>';

		$text = legacyTextConverter::comment($stored);

		$this->assertStringContains('4,5', $text);
		$this->assertStringNotContains('rollContainer', $text);
	}

	// ─── Forged markers ───────────────────────────────────────────

	public function testAMarkerAPosterTypedIsStrippedNotHonoured(): void {
		$stored = 'nice roll [[koko:dice:1d1:1]] eh';

		$this->assertSame('nice roll  eh', legacyTextConverter::comment($stored));
	}

	public function testAForgedMarkerIsStrippedBeforeRealOnesAreAdded(): void {
		$stored = '[[koko:dice:1d1:1]]<span class="rollContainer">dice1d6=<span class="roll">4</span></span>';

		$this->assertSame("\n[[koko:dice:1d6:4]]", legacyTextConverter::comment($stored));
	}

	// ─── Fortunes ─────────────────────────────────────────────────

	public function testAFortuneBecomesItsIndex(): void {
		$stored = 'hi<p class="fortune" style="color: #aabbcc;">Your fortune: Small luck</p>';

		$this->assertSame("hi\n[[koko:fortune:1]]", legacyTextConverter::comment($stored, self::FORTUNES));
	}

	public function testAFortuneRoundTripsBackToTheSameLine(): void {
		$text = legacyTextConverter::comment(
			'<p class="fortune" style="color: #aabbcc;">Your fortune: Bad luck</p>',
			self::FORTUNES
		);

		$this->assertStringContains('Your fortune: Bad luck', $this->reformat($text));
	}

	public function testAFortuneNoLongerInTheListKeepsItsText(): void {
		$stored = '<p class="fortune" style="color: #aabbcc;">Your fortune: Retired luck</p>';

		$this->assertSame("\nYour fortune: Retired luck", legacyTextConverter::comment($stored, self::FORTUNES));
	}

	public function testAFortuneWithNoListToHandKeepsItsText(): void {
		$stored = '<p class="fortune" style="color: #aabbcc;">Your fortune: Great luck</p>';

		$this->assertSame("\nYour fortune: Great luck", legacyTextConverter::comment($stored));
	}

	// ─── Fields ───────────────────────────────────────────────────

	public function testFieldsAreDecoded(): void {
		$this->assertSame('a & b', legacyTextConverter::field('a &amp; b'));
	}

	public function testAFieldsEscapedMarkupComesBackAsText(): void {
		$this->assertSame('<b>name</b>', legacyTextConverter::field('&lt;b&gt;name&lt;/b&gt;'));
	}

	/** Very old rows kept trip and capcode markup in the name column; only its text survives. */
	public function testNameMarkupIsReducedToItsText(): void {
		$stored = '<span class="postername">Anon</span><span class="postertrip">◆abc123</span>';

		$this->assertSame('Anon◆abc123', legacyTextConverter::field($stored));
	}

	public function testAFieldSurvivesTheRoundTrip(): void {
		$this->assertSame(
			'a &amp; b',
			commentFormatter::fieldToHtml(legacyTextConverter::field('a &amp; b'), textFormat::PLAIN_TEXT)
		);
	}

	public function testEmptyInputStaysEmpty(): void {
		$this->assertSame('', legacyTextConverter::comment(''));
		$this->assertSame('', legacyTextConverter::field(''));
	}
}
