<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\edit\editPostFields;
use Kokonotsuba\post\textFormat;

/**
 * Unit tests for the edit form's comment conversions.
 *
 * A legacy comment is HTML; the form shows it with its line breaks as newlines and nothing else
 * touched. A plain-text comment already is the text to edit and round-trips untouched. Both
 * directions have to be lossless, or a moderator fixing a typo silently rewrites the rest of
 * the post.
 */
final class EditPostFieldsTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('edit/editPostFields.php');
	}

	public function testBreakTagsBecomeNewlines(): void {
		$this->assertSame("one\ntwo", editPostFields::commentToEditableText('one<br>two', textFormat::LEGACY_HTML));
	}

	public function testEveryBreakSpellingBecomesANewline(): void {
		$this->assertSame(
			"a\nb\nc\nd",
			editPostFields::commentToEditableText('a<br>b<br/>c<br />d', textFormat::LEGACY_HTML)
		);
	}

	public function testUppercaseBreakTagsBecomeNewlines(): void {
		$this->assertSame("a\nb", editPostFields::commentToEditableText('a<BR>b', textFormat::LEGACY_HTML));
	}

	public function testEntitiesAndMarkupSurviveTheTripToTheForm(): void {
		$stored = '&gt;&gt;1<br>with &lt;b&gt;markup&lt;/b&gt; &amp; entities';

		$this->assertSame(
			">>1\nwith &lt;b&gt;markup&lt;/b&gt; &amp; entities",
			str_replace('&gt;&gt;', '>>', editPostFields::commentToEditableText($stored, textFormat::LEGACY_HTML))
		);
	}

	public function testQuoteEntitiesAreNotDecoded(): void {
		// The quote link renderer matches &gt;&gt;, so decoding these would break every quote.
		$this->assertSame('&gt;&gt;123', editPostFields::commentToEditableText('&gt;&gt;123', textFormat::LEGACY_HTML));
	}

	public function testStoredAnchorsAreLeftAlone(): void {
		$stored = 'see <a href="https://example.com">this</a>';
		$this->assertSame($stored, editPostFields::commentToEditableText($stored, textFormat::LEGACY_HTML));
	}

	public function testNewlinesBecomeBreakTags(): void {
		$this->assertSame('one<br>two', editPostFields::editableTextToComment("one\ntwo", textFormat::LEGACY_HTML));
	}

	public function testStoredCommentNeverKeepsRawNewlines(): void {
		$stored = editPostFields::editableTextToComment("one\ntwo\nthree", textFormat::LEGACY_HTML);

		$this->assertStringNotContains("\n", $stored);
		$this->assertSame('one<br>two<br>three', $stored);
	}

	public function testWindowsAndMacLineEndingsAreNormalized(): void {
		$this->assertSame('a<br>b<br>c', editPostFields::editableTextToComment("a\r\nb\rc", textFormat::LEGACY_HTML));
	}

	public function testBlankLinesAreKept(): void {
		$this->assertSame('a<br><br>b', editPostFields::editableTextToComment("a\n\nb", textFormat::LEGACY_HTML));
	}

	public function testEmptyStringsStayEmpty(): void {
		$this->assertSame('', editPostFields::commentToEditableText('', textFormat::LEGACY_HTML));
		$this->assertSame('', editPostFields::editableTextToComment('', textFormat::LEGACY_HTML));
	}

	public function testRoundTripLeavesAStoredCommentUnchanged(): void {
		$stored = '&gt;&gt;1<br>reply<br><br>with &lt;b&gt;markup&lt;/b&gt; &amp; entities';

		$this->assertSame(
			$stored,
			editPostFields::editableTextToComment(editPostFields::commentToEditableText($stored, textFormat::LEGACY_HTML), textFormat::LEGACY_HTML)
		);
	}

	public function testRoundTripLeavesEditedTextUnchanged(): void {
		$text = "first line\n\nthird line";

		$this->assertSame(
			$text,
			editPostFields::commentToEditableText(editPostFields::editableTextToComment($text, textFormat::LEGACY_HTML), textFormat::LEGACY_HTML)
		);
	}

	public function testRoundTripIsStableAcrossRepeatedEdits(): void {
		$stored = 'line one<br>line two';

		for ($i = 0; $i < 5; $i++) {
			$stored = editPostFields::editableTextToComment(editPostFields::commentToEditableText($stored, textFormat::LEGACY_HTML), textFormat::LEGACY_HTML);
		}

		$this->assertSame('line one<br>line two', $stored);
	}

	public function testMultibyteTextIsUntouched(): void {
		$this->assertSame(
			"日本語のテキスト\n絵文字 🍣",
			editPostFields::commentToEditableText('日本語のテキスト<br>絵文字 🍣', textFormat::LEGACY_HTML)
		);
	}

	public function testTagsThatMerelyStartLikeABreakAreLeftAlone(): void {
		$stored = '<break>not a line break</break>';
		$this->assertSame($stored, editPostFields::commentToEditableText($stored, textFormat::LEGACY_HTML));
	}

	// ─── Plain-text posts ─────────────────────────────────────────

	public function testPlainTextCommentReachesTheFormUnchanged(): void {
		$stored = ">>1\nreply with <b>markup</b> & entities";

		$this->assertSame($stored, editPostFields::commentToEditableText($stored, textFormat::PLAIN_TEXT));
	}

	public function testPlainTextEditKeepsItsNewlines(): void {
		$text = "one\ntwo\n\nfour";

		$this->assertSame($text, editPostFields::editableTextToComment($text, textFormat::PLAIN_TEXT));
	}

	public function testPlainTextEditNeverAddsBreakTags(): void {
		$stored = editPostFields::editableTextToComment("one\ntwo", textFormat::PLAIN_TEXT);

		$this->assertStringNotContains('<br', $stored);
	}

	public function testPlainTextEditNormalizesLineEndings(): void {
		$this->assertSame("a\nb\nc", editPostFields::editableTextToComment("a\r\nb\rc", textFormat::PLAIN_TEXT));
	}

	public function testPlainTextRoundTripIsStableAcrossRepeatedEdits(): void {
		$stored = "line one\nline two";

		for ($i = 0; $i < 5; $i++) {
			$stored = editPostFields::editableTextToComment(
				editPostFields::commentToEditableText($stored, textFormat::PLAIN_TEXT),
				textFormat::PLAIN_TEXT
			);
		}

		$this->assertSame("line one\nline two", $stored);
	}

	public function testDiceMarkersSurviveAnEditSoTheRollIsNotLost(): void {
		$stored = "rolling\n[[koko:dice:2d6:4,5]]";

		$this->assertSame(
			$stored,
			editPostFields::editableTextToComment(
				editPostFields::commentToEditableText($stored, textFormat::PLAIN_TEXT),
				textFormat::PLAIN_TEXT
			)
		);
	}

	public function testRawHtmlCommentIsTreatedAsMarkup(): void {
		// A raw-HTML post's comment is emitted as-is, so the form edits it as markup.
		$this->assertSame("a\nb", editPostFields::commentToEditableText('a<br>b', textFormat::RAW_HTML));
		$this->assertSame('a<br>b', editPostFields::editableTextToComment("a\nb", textFormat::RAW_HTML));
	}
}
