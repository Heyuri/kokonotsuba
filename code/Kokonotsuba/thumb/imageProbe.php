<?php

namespace Kokonotsuba\thumb;

/**
 * Reads image containers directly to answer three questions the upload's extension
 * cannot: what format is this really, does it hold more than one frame, and which
 * way up is it meant to be shown.
 */
final class imageProbe {
	public const JPEG = 'jpeg';
	public const PNG = 'png';
	public const GIF = 'gif';
	public const WEBP = 'webp';
	public const BMP = 'bmp';
	public const AVIF = 'avif';
	public const UNKNOWN = '';

	/** ISO base media brands that mean "this is an AVIF". */
	private const AVIF_BRANDS = ['avif', 'avis', 'av01'];

	/**
	 * Identify a file from its leading bytes.
	 *
	 * @return string One of the class constants, or UNKNOWN.
	 */
	public static function detectType(string $file): string {
		$header = self::readHead($file, 32);

		if (strlen($header) < 12) {
			return self::UNKNOWN;
		}

		if (str_starts_with($header, "\xFF\xD8\xFF")) {
			return self::JPEG;
		}

		if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
			return self::PNG;
		}

		if (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
			return self::GIF;
		}

		if (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
			return self::WEBP;
		}

		if (str_starts_with($header, 'BM')) {
			return self::BMP;
		}

		if (substr($header, 4, 4) === 'ftyp' && in_array(substr($header, 8, 4), self::AVIF_BRANDS, true)) {
			return self::AVIF;
		}

		return self::UNKNOWN;
	}

	/**
	 * Whether the file holds more than one frame. Covers animated GIF, animated WebP,
	 * APNG and AVIF image sequences; anything else is reported as still.
	 */
	public static function isAnimated(string $file): bool {
		return match (self::detectType($file)) {
			self::GIF => self::gifIsAnimated($file),
			self::WEBP => self::webpIsAnimated($file),
			self::PNG => self::pngIsAnimated($file),
			self::AVIF => substr(self::readHead($file, 12), 8, 4) === 'avis',
			default => false,
		};
	}

	/**
	 * EXIF orientation of a JPEG, 1 through 8. Browsers apply this when displaying the
	 * full image, so thumbnails have to as well or they come out sideways.
	 *
	 * @return int 1 when there is no usable orientation tag.
	 */
	public static function orientation(string $file): int {
		if (!function_exists('exif_read_data') || self::detectType($file) !== self::JPEG) {
			return 1;
		}

		$exif = @exif_read_data($file);
		$orientation = (int)($exif['Orientation'] ?? 1);

		return ($orientation >= 1 && $orientation <= 8) ? $orientation : 1;
	}

	/** Whether the orientation swaps the image's width and height. */
	public static function orientationSwapsAxes(int $orientation): bool {
		return $orientation >= 5 && $orientation <= 8;
	}

	/** Read the first $length bytes of a file, or as many as there are. */
	private static function readHead(string $file, int $length): string {
		$handle = @fopen($file, 'rb');

		if ($handle === false) {
			return '';
		}

		$header = (string)fread($handle, $length);
		fclose($handle);

		return $header;
	}

	/**
	 * An extended WebP carries a VP8X chunk whose flag byte marks animation. A plain
	 * lossy or lossless WebP has no VP8X and is always a single frame.
	 */
	private static function webpIsAnimated(string $file): bool {
		$header = self::readHead($file, 21);

		if (strlen($header) < 21 || substr($header, 12, 4) !== 'VP8X') {
			return false;
		}

		return (ord($header[20]) & 0x02) !== 0;
	}

	/** An APNG announces itself with an acTL chunk, which must appear before the first IDAT. */
	private static function pngIsAnimated(string $file): bool {
		$handle = @fopen($file, 'rb');

		if ($handle === false) {
			return false;
		}

		try {
			// skip the eight byte signature
			if (fseek($handle, 8) !== 0) {
				return false;
			}

			while (true) {
				$chunkHeader = fread($handle, 8);

				if (strlen((string)$chunkHeader) < 8) {
					return false;
				}

				['length' => $length, 'type' => $type] = unpack('Nlength/a4type', $chunkHeader);

				if ($type === 'acTL') {
					return true;
				}

				// image data has started, so there was no acTL
				if ($type === 'IDAT' || $type === 'IEND') {
					return false;
				}

				// payload plus the four byte CRC
				if (fseek($handle, $length + 4, SEEK_CUR) !== 0) {
					return false;
				}
			}
		} finally {
			fclose($handle);
		}
	}

	/**
	 * Walk the GIF block stream and stop as soon as a second image descriptor turns up.
	 * Counting graphic control extensions instead would miscount, since a still GIF may
	 * carry one for its transparent colour.
	 */
	private static function gifIsAnimated(string $file): bool {
		$handle = @fopen($file, 'rb');

		if ($handle === false) {
			return false;
		}

		try {
			$header = fread($handle, 13);

			if (strlen((string)$header) < 13) {
				return false;
			}

			// the global colour table, when present, sits between the header and the blocks
			if (!self::skipGifColourTable($handle, ord($header[10]))) {
				return false;
			}

			$frames = 0;

			while (true) {
				$block = fread($handle, 1);

				if ((string)$block === '') {
					return false;
				}

				switch (ord($block)) {
					case 0x21: // extension: one label byte then sub-blocks
						if ((string)fread($handle, 1) === '') {
							return false;
						}

						if (!self::skipGifSubBlocks($handle)) {
							return false;
						}
						break;

					case 0x2C: // image descriptor: one frame
						if (++$frames > 1) {
							return true;
						}

						$descriptor = fread($handle, 9);

						if (strlen((string)$descriptor) < 9) {
							return false;
						}

						if (!self::skipGifColourTable($handle, ord($descriptor[8]))) {
							return false;
						}

						// LZW minimum code size, then the compressed sub-blocks
						if ((string)fread($handle, 1) === '') {
							return false;
						}

						if (!self::skipGifSubBlocks($handle)) {
							return false;
						}
						break;

					default: // trailer, or a stream we cannot follow
						return false;
				}
			}
		} finally {
			fclose($handle);
		}
	}

	/**
	 * Skip a GIF colour table when the packed field's high bit says one follows.
	 *
	 * @param resource $handle
	 * @param int $packedField Packed byte from the screen descriptor or image descriptor.
	 */
	private static function skipGifColourTable($handle, int $packedField): bool {
		if (!($packedField & 0x80)) {
			return true;
		}

		$entries = 1 << (($packedField & 0x07) + 1);

		return fseek($handle, $entries * 3, SEEK_CUR) === 0;
	}

	/**
	 * Skip a run of GIF sub-blocks, each prefixed with its length and terminated by a
	 * zero length block.
	 *
	 * @param resource $handle
	 */
	private static function skipGifSubBlocks($handle): bool {
		while (true) {
			$lengthByte = fread($handle, 1);

			if ((string)$lengthByte === '') {
				return false;
			}

			$length = ord($lengthByte);

			if ($length === 0) {
				return true;
			}

			if (fseek($handle, $length, SEEK_CUR) !== 0) {
				return false;
			}
		}
	}
}
