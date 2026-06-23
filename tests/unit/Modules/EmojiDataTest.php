<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;

/**
 * Sanity tests for the emoji module's data table (emoji → slug map).
 *
 * Guards against the data file being accidentally corrupted into a non-array or
 * gaining non-string slugs (which the renderer would choke on).
 */
final class EmojiDataTest extends TestCase {

	private function load(): array {
		// Plain include (not _once) so each test gets the returned array.
		return include KOKO_TEST_ROOT . '/module/emoji/emojis.php';
	}

	public function testReturnsNonEmptyArray(): void {
		$emojis = $this->load();
		$this->assertIsArray($emojis);
		$this->assertGreaterThan(0, count($emojis));
	}

	public function testAllValuesAreNonEmptyStrings(): void {
		foreach ($this->load() as $emoji => $slug) {
			$this->assertIsString($slug);
			$this->assertNotSame('', $slug);
		}
		$this->pass();
	}

	public function testKnownMappingPresent(): void {
		$emojis = $this->load();
		$this->assertSame('Grinning-Face-with-Smiling-Eyes', $emojis['😄'] ?? null);
	}

	public function testSlugsHaveNoWhitespace(): void {
		// Slugs are used to build image filenames, so they must not contain
		// spaces.
		foreach ($this->load() as $slug) {
			$this->assertFalse(str_contains($slug, ' '), "slug '$slug' contains a space");
		}
		$this->pass();
	}
}
