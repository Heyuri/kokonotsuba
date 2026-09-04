<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\commentMarker;

/**
 * Markers for content rolled at post time and drawn at render time.
 *
 * Two properties matter. A marker has to survive htmlspecialchars() unchanged, because it is
 * put in the comment before escaping and matched after it. And a marker a poster typed has to
 * be strippable, or anyone could write themselves a winning dice roll.
 */
final class CommentMarkerTest extends TestCase {

	public function testMarkerSurvivesEscaping(): void {
		$marker = commentMarker::make('dice', '2d6+1:4,5');

		$this->assertSame($marker, htmlspecialchars($marker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	}

	public function testPayloadDropsCharactersThatWouldNotSurviveEscaping(): void {
		$marker = commentMarker::make('dice', '<script>&"\'');

		$this->assertSame('[[koko:dice:script]]', $marker);
	}

	public function testExpandReplacesOnlyTheNamedKind(): void {
		$html = commentMarker::make('dice', '1,2') . commentMarker::make('fortune', '3');

		$expanded = commentMarker::expand($html, 'dice', fn(string $p): string => "<b>$p</b>");

		$this->assertSame('<b>1,2</b>[[koko:fortune:3]]', $expanded);
	}

	public function testExpandPassesThePayloadToTheHandler(): void {
		$seen = '';

		commentMarker::expand(commentMarker::make('dice', '2d6:4,5'), 'dice', function (string $p) use (&$seen): string {
			$seen = $p;
			return '';
		});

		$this->assertSame('2d6:4,5', $seen);
	}

	/** 'diceemail' must not be mistaken for 'dice', which the trailing colon is what prevents. */
	public function testKindsSharingAPrefixDoNotCollide(): void {
		$html = commentMarker::make('diceemail', '4,5');

		$this->assertSame($html, commentMarker::expand($html, 'dice', fn(string $p): string => 'WRONG'));
	}

	public function testExpandLeavesTextWithNoMarkersAlone(): void {
		$html = 'just a comment, no markers here';

		$this->assertSame($html, commentMarker::expand($html, 'dice', fn(string $p): string => 'X'));
	}

	public function testStripRemovesAForgedMarker(): void {
		$this->assertSame(
			'nice roll  eh',
			commentMarker::strip('nice roll [[koko:dice:1d1:1]] eh')
		);
	}

	public function testStripRemovesEveryKind(): void {
		$typed = 'a' . commentMarker::make('dice', '1') . 'b' . commentMarker::make('fortune', '2') . 'c';

		$this->assertSame('abc', commentMarker::strip($typed));
	}

	public function testStripLeavesOrdinaryBracketsAlone(): void {
		$text = '[[not a marker]] and [koko:dice:1]';

		$this->assertSame($text, commentMarker::strip($text));
	}

	public function testMarkerCannotSpanALineBreak(): void {
		// Bounded to one line, so a marker can never swallow the rest of a comment.
		$text = "[[koko:dice:1\n,2]]";

		$this->assertSame($text, commentMarker::strip($text));
	}
}
