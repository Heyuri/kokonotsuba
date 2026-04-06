<?php

namespace Kokonotsuba\Modules\perceptualBan;

class perceptualHasher {
	private const HASH_SIZE = 8;
	private const RESIZE_WIDTH = 9; // 9 wide for 8 horizontal differences
	private const RESIZE_HEIGHT = 8;

	private const IMAGE_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/bmp',
	];

	/**
	 * Check if a MIME type is a hashable image type.
	 */
	public function isHashableImage(string $mimeType): bool {
		return in_array($mimeType, self::IMAGE_MIME_TYPES, true);
	}

	/**
	 * Check if a MIME type is an animated format that should use a thumbnail.
	 */
	public function isAnimatedFormat(string $mimeType): bool {
		return $mimeType === 'image/gif';
	}

	/**
	 * Compute a 64-bit difference hash (dHash) of an image file.
	 * Returns a hex string (16 chars) or null if the image can't be processed.
	 */
	public function computeHash(string $filePath): ?string {
		$source = $this->createImageFromFile($filePath);
		if (!$source) {
			return null;
		}

		// resize to (RESIZE_WIDTH x RESIZE_HEIGHT) using grayscale
		$resized = imagecreatetruecolor(self::RESIZE_WIDTH, self::RESIZE_HEIGHT);
		imagecopyresampled($resized, $source, 0, 0, 0, 0, self::RESIZE_WIDTH, self::RESIZE_HEIGHT, imagesx($source), imagesy($source));
		unset($source);

		// convert to grayscale intensities
		$pixels = [];
		for ($y = 0; $y < self::RESIZE_HEIGHT; $y++) {
			for ($x = 0; $x < self::RESIZE_WIDTH; $x++) {
				$rgb = imagecolorat($resized, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				// luminosity grayscale
				$pixels[$y][$x] = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
			}
		}
		unset($resized);

		// compute difference hash: compare each pixel to its right neighbor
		$hash = 0;
		$bit = 0;
		for ($y = 0; $y < self::RESIZE_HEIGHT; $y++) {
			for ($x = 0; $x < self::HASH_SIZE; $x++) {
				if ($pixels[$y][$x] < $pixels[$y][$x + 1]) {
					$hash |= (1 << $bit);
				}
				$bit++;
			}
		}

		return sprintf('%016x', $hash);
	}

	/**
	 * Compute hamming distance between two hex hash strings.
	 */
	public function hammingDistance(string $hash1, string $hash2): int {
		$int1 = $this->hexToInt($hash1);
		$int2 = $this->hexToInt($hash2);

		// XOR the two values and count differing bits
		$xor = $int1 ^ $int2;

		// count set bits
		$count = 0;
		// handle 64-bit on both 32-bit and 64-bit PHP
		for ($i = 0; $i < 64; $i++) {
			if ($xor & (1 << $i)) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Convert a 16-char hex string to an integer for DB storage.
	 */
	public function hexToInt(string $hex): int {
		// Use intval with base 16 - on 64-bit PHP this handles the full range
		// For values that would overflow signed int64, we use a two-step approach
		$high = hexdec(substr($hex, 0, 8));
		$low = hexdec(substr($hex, 8, 8));
		return ($high << 32) | $low;
	}

	/**
	 * Convert an integer back to a 16-char hex string.
	 */
	public function intToHex(int $value): string {
		return sprintf('%016x', $value);
	}

	/**
	 * Extract a frame from an animated file (GIF/video) using ffmpeg,
	 * then compute its hash. Cleans up the temp file after.
	 *
	 * @param string $filePath Path to the animated file (GIF or video)
	 * @return string|null 16-character hex hash, or null if it can't be processed
	 */
	public function computeHashFromAnimated(string $filePath): ?string {
		if (!file_exists($filePath)) {
			return null;
		}

		// get duration via ffprobe
		$cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath);
		$duration = (float) exec($cmd);

		$timestamp = ($duration > 0.1) ? $duration / 3 + 1 : 0;

		$formattedTimestamp = gmdate("H:i:s", (int) $timestamp) . '.' . str_pad((string) (((int) ($timestamp * 1000)) % 1000), 3, '0', STR_PAD_LEFT);

		// extract frame to temp file
		$tmpFile = tempnam(sys_get_temp_dir(), 'phash_anim_') . '.jpg';
		$escapedFile = escapeshellarg($filePath);
		$escapedOutput = escapeshellarg($tmpFile);
		$escapedTimestamp = escapeshellarg($formattedTimestamp);

		$cmd = "ffmpeg -y -ss {$escapedTimestamp} -i {$escapedFile} -vframes 1 {$escapedOutput} 2>&1";
		exec($cmd, $output, $returnCode);

		if ($returnCode !== 0 || !file_exists($tmpFile)) {
			@unlink($tmpFile);
			// fall back to GD first-frame method for GIFs
			return $this->computeHashFromGifFirstFrame($filePath);
		}

		$hash = $this->computeHash($tmpFile);
		@unlink($tmpFile);

		return $hash;
	}

	/**
	 * Fallback: flatten an animated GIF first frame via GD and compute its hash.
	 * Used when ffmpeg is unavailable or fails.
	 *
	 * @param string $gifPath Path to the GIF file
	 * @return string|null 16-character hex hash, or null if it can't be processed
	 */
	private function computeHashFromGifFirstFrame(string $gifPath): ?string {
		$source = @imagecreatefromgif($gifPath);
		if (!$source) {
			return null;
		}

		$width = imagesx($source);
		$height = imagesy($source);
		$flat = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($flat, 255, 255, 255);
		imagefill($flat, 0, 0, $white);
		imagecopy($flat, $source, 0, 0, 0, 0, $width, $height);
		unset($source);

		$tmpFile = tempnam(sys_get_temp_dir(), 'phash_gif_');
		imagejpeg($flat, $tmpFile, 90);
		unset($flat);

		$hash = $this->computeHash($tmpFile);
		@unlink($tmpFile);

		return $hash;
	}

	private function createImageFromFile(string $filePath): ?\GdImage {
		if (!file_exists($filePath)) {
			return null;
		}

		$imageInfo = @getimagesize($filePath);
		if ($imageInfo === false) {
			return null;
		}

		$mimeType = $imageInfo['mime'] ?? '';

		$image = match ($mimeType) {
			'image/jpeg' => @imagecreatefromjpeg($filePath),
			'image/png' => @imagecreatefrompng($filePath),
			'image/gif' => @imagecreatefromgif($filePath),
			'image/webp' => @imagecreatefromwebp($filePath),
			'image/bmp' => @imagecreatefrombmp($filePath),
			default => false,
		};

		return $image ?: null;
	}
}
