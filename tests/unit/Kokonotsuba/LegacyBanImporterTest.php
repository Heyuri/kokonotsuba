<?php

namespace Koko\Tests\Unit\Kokonotsuba;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\ban\legacyBanImporter;

/** Parsing of the old `ip,start,expires,reason` ban lines. */
final class LegacyBanImporterTest extends TestCase {

	public function testParsesAFullLine(): void {
		$entry = legacyBanImporter::parseLine("1.2.3.4,1700000000,1700086400,spam&#44; again\n");

		$this->assertSame('1.2.3.4', $entry['pattern']);
		$this->assertSame(1700000000, $entry['start']);
		$this->assertSame(1700086400, $entry['expires']);
		$this->assertSame('spam, again', $entry['reason']);
	}

	public function testReasonIsOptional(): void {
		$this->assertSame('', legacyBanImporter::parseLine('1.2.3.*,1,2')['reason']);
	}

	public function testRejectsBlankAndMalformedLines(): void {
		$this->assertNull(legacyBanImporter::parseLine(''));
		$this->assertNull(legacyBanImporter::parseLine("   \n"));
		$this->assertNull(legacyBanImporter::parseLine('1.2.3.4,1700000000'));
		$this->assertNull(legacyBanImporter::parseLine(',1,2,no pattern'));
	}
}
