<?php

namespace Koko\Tests\Unit\Modules;

use Koko\Tests\Framework\TestCase;
use Kokonotsuba\Modules\perceptualBan\perceptualHasher;

/**
 * Unit tests for the perceptualBan dHash implementation.
 *
 * MIME classification, hamming distance and hex<->int conversion are pure. The
 * actual image hashing needs the GD extension; those tests are skipped (passing
 * trivially) when GD is unavailable.
 */
final class PerceptualHasherTest extends TestCase {

	protected function setUp(): void {
		requireModuleFile('perceptualBan/perceptualHasher.php');
	}

	private function hasher(): perceptualHasher {
		return new perceptualHasher();
	}

	public function testIsVideoFormat(): void {
		$h = $this->hasher();
		$this->assertTrue($h->isVideoFormat('video/mp4'));
		$this->assertTrue($h->isVideoFormat('video/webm'));
		$this->assertFalse($h->isVideoFormat('image/png'));
	}

	public function testIsHashableMedia(): void {
		$h = $this->hasher();
		$this->assertTrue($h->isHashableMedia('image/png'));
		$this->assertTrue($h->isHashableMedia('image/gif'));
		$this->assertTrue($h->isHashableMedia('video/mp4'));
		$this->assertFalse($h->isHashableMedia('application/pdf'));
		$this->assertFalse($h->isHashableMedia('text/plain'));
	}

	public function testNeedsFrameExtraction(): void {
		$h = $this->hasher();
		$this->assertTrue($h->needsFrameExtraction('image/gif'));
		$this->assertTrue($h->needsFrameExtraction('video/mp4'));
		$this->assertFalse($h->needsFrameExtraction('image/png'));
		$this->assertFalse($h->needsFrameExtraction('image/jpeg'));
	}

	public function testHexIntRoundTrip(): void {
		$h = $this->hasher();
		$this->assertSame('00000000000000ff', $h->intToHex(255));
		$this->assertSame(255, $h->hexToInt('00000000000000ff'));
		$this->assertSame('000000000000ffff', $h->intToHex(65535));

		$value = 0x0123456789abcdef;
		$this->assertSame($value, $h->hexToInt($h->intToHex($value)));
	}

	public function testHammingDistanceOfIdenticalHashesIsZero(): void {
		$h = $this->hasher();
		$this->assertSame(0, $h->hammingDistance('abcdef0123456789', 'abcdef0123456789'));
	}

	public function testHammingDistanceCountsDifferingBits(): void {
		$h = $this->hasher();
		// 0x...0 vs 0x...1 differ in exactly one bit.
		$this->assertSame(1, $h->hammingDistance('0000000000000000', '0000000000000001'));
		// 0x3 has two bits set vs 0x0.
		$this->assertSame(2, $h->hammingDistance('0000000000000000', '0000000000000003'));
		// 0xF == 4 bits.
		$this->assertSame(4, $h->hammingDistance('0000000000000000', '000000000000000f'));
	}

	public function testComputeHashIsDeterministicAndWellFormed(): void {
		if (!function_exists('imagecreatetruecolor')) {
			$this->pass(); // GD not available in this environment
			return;
		}

		$file = $this->makeGradientPng();
		try {
			$hash = $this->hasher()->computeHash($file);
			$this->assertNotNull($hash);
			$this->assertSame(16, strlen($hash));
			$this->assertTrue(ctype_xdigit($hash));
			// Same file → same hash.
			$this->assertSame($hash, $this->hasher()->computeHash($file));
		} finally {
			@unlink($file);
		}
	}

	public function testComputeHashReturnsNullForMissingFile(): void {
		$this->assertNull($this->hasher()->computeHash('/no/such/image.png'));
	}

	/** Write a small horizontal gradient PNG to a temp file and return its path. */
	private function makeGradientPng(): string {
		$img = imagecreatetruecolor(32, 32);
		for ($x = 0; $x < 32; $x++) {
			$c = imagecolorallocate($img, $x * 8 % 256, 0, 0);
			imageline($img, $x, 0, $x, 31, $c);
		}
		$path = tempnam(sys_get_temp_dir(), 'koko_phash_') . '.png';
		imagepng($img, $path);
		imagedestroy($img);
		return $path;
	}
}
