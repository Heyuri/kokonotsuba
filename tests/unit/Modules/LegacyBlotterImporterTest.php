<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\blotter\legacyBlotterImporter;
use RuntimeException;

requireModuleFile('blotter/legacyBlotterImporter.php');

/** Parsing of the old `date<>comment<>uid` blotter file. */
final class LegacyBlotterImporterTest extends TestCase {

	public function testNormalizesEveryLegacyDateShape(): void {
		$this->assertSame('2020-01-02 03:04:05', legacyBlotterImporter::normalizeLegacyDate('2020/01/02 03:04:05'));
		$this->assertSame('2020-01-02 03:04:05', legacyBlotterImporter::normalizeLegacyDate('2020-01-02 03:04:05'));
		$this->assertSame('2020-01-02 00:00:00', legacyBlotterImporter::normalizeLegacyDate(' 2020/01/02 '));
		$this->assertSame('2020-01-02 00:00:00', legacyBlotterImporter::normalizeLegacyDate('2020-01-02'));
	}

	public function testRejectsAnUnreadableDate(): void {
		$this->assertThrows(fn() => legacyBlotterImporter::normalizeLegacyDate('not a date'), RuntimeException::class);
	}

	public function testParsesAFile(): void {
		$path = tempnam(sys_get_temp_dir(), 'blotter');
		file_put_contents($path, "2020/01/02<>Hello there<>abc\n\n2020-03-04 05:06:07<>No uid\n");

		try {
			$entries = legacyBlotterImporter::parseFile($path);
		} finally {
			unlink($path);
		}

		$this->assertSame(2, count($entries));
		$this->assertSame('2020-01-02 00:00:00', $entries[0]['date_added']);
		$this->assertSame('Hello there', $entries[0]['blotter_content']);
		$this->assertSame('abc', $entries[0]['legacy_uid']);
		$this->assertNull($entries[1]['legacy_uid']);
	}

	public function testRejectsALineWithoutASeparator(): void {
		$path = tempnam(sys_get_temp_dir(), 'blotter');
		file_put_contents($path, "just text\n");

		try {
			$this->assertThrows(fn() => legacyBlotterImporter::parseFile($path), RuntimeException::class);
		} finally {
			unlink($path);
		}
	}
}
