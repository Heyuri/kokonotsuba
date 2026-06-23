<?php

namespace Koko\Tests\Unit\Puchiko;

use Koko\Tests\Framework\TestCase;

use function Puchiko\normalize\stripInvisible;
use function Puchiko\normalize\toFilterString;
use function Puchiko\normalize\toLowerFilterString;

/**
 * Unit tests for the Puchiko\normalize spam/filter normalisation pipeline.
 *
 * NFKC folding depends on the intl extension; these tests assert behaviour that
 * holds with intl present (the supported stack ships it).
 */
final class NormalizeTest extends TestCase {

	public function testStripInvisibleRemovesZeroWidth(): void {
		// Zero-width space between letters must vanish.
		$this->assertSame('ab', stripInvisible("a\u{200B}b"));
		// BOM / zero-width no-break space.
		$this->assertSame('ab', stripInvisible("a\u{FEFF}b"));
	}

	public function testStripInvisibleRemovesVariationSelectors(): void {
		$this->assertSame('A', stripInvisible("A\u{FE0F}"));
	}

	public function testStripInvisiblePreservesNormalWhitespace(): void {
		$this->assertSame("a b\tc\nd", stripInvisible("a b\tc\nd"));
	}

	public function testToFilterStringFoldsFullwidth(): void {
		// Fullwidth Latin folds to ASCII via NFKC.
		$this->assertSame('ABC', toFilterString('ＡＢＣ'));
	}

	public function testToLowerFilterStringLowercases(): void {
		$this->assertSame('abc', toLowerFilterString('ABC'));
		$this->assertSame('abc', toLowerFilterString('ＡＢＣ'));
	}

	public function testToFilterStringStripsInvisiblesBeforeComparing(): void {
		// A bypass attempt: zero-width spaces inside a word collapse away,
		// so "b​a​d" normalises to the same thing as "bad".
		$this->assertSame(toFilterString('bad'), toFilterString("b\u{200B}a\u{200B}d"));
	}

	public function testToFilterStringIsIdempotent(): void {
		$once = toFilterString('Ｈello　World');
		$this->assertSame($once, toFilterString($once));
	}
}
