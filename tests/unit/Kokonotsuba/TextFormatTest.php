<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\post\textFormat;

/**
 * The per-post text storage format.
 *
 * The stored integers are part of the on-disk format, and 0 has to keep meaning "legacy HTML"
 * so that every row written before the column existed still renders the way it always did.
 */
final class TextFormatTest extends TestCase {

	public function testStoredValuesAreStable(): void {
		$this->assertSame(0, textFormat::LEGACY_HTML->value);
		$this->assertSame(1, textFormat::PLAIN_TEXT->value);
		$this->assertSame(2, textFormat::RAW_HTML->value);
	}

	public function testUnsetColumnReadsAsLegacy(): void {
		$this->assertSame(textFormat::LEGACY_HTML, textFormat::fromStored(0));
		$this->assertSame(textFormat::LEGACY_HTML, textFormat::fromStored(null));
		$this->assertSame(textFormat::LEGACY_HTML, textFormat::fromStored(''));
	}

	public function testUnknownValuesFallBackToLegacyRatherThanThrowing(): void {
		// A row from a newer schema must never make an older renderer escape legacy markup.
		$this->assertSame(textFormat::LEGACY_HTML, textFormat::fromStored(99));
	}

	public function testStringColumnValuesAreAccepted(): void {
		// PDO hands back strings for integer columns unless emulation is off.
		$this->assertSame(textFormat::PLAIN_TEXT, textFormat::fromStored('1'));
		$this->assertSame(textFormat::RAW_HTML, textFormat::fromStored('2'));
	}

	public function testOnlyPlainTextCommentsAreEscaped(): void {
		$this->assertTrue(textFormat::LEGACY_HTML->commentIsHtml());
		$this->assertTrue(textFormat::RAW_HTML->commentIsHtml());
		$this->assertFalse(textFormat::PLAIN_TEXT->commentIsHtml());
	}

	/** A raw-HTML post only exempts its comment; its name and subject are still escaped. */
	public function testOnlyLegacyFieldsAreLeftUnescaped(): void {
		$this->assertTrue(textFormat::LEGACY_HTML->fieldsAreHtml());
		$this->assertFalse(textFormat::RAW_HTML->fieldsAreHtml());
		$this->assertFalse(textFormat::PLAIN_TEXT->fieldsAreHtml());
	}
}
