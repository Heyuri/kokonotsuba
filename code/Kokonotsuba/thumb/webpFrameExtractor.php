<?php

namespace Kokonotsuba\thumb;

use Generator;

/**
 * Pulls the first frame out of an animated WebP and rewrites it as a still one.
 *
 * Neither GD nor ffmpeg can decode an animated WebP, so without this an upload in that
 * format gets no thumbnail at all. The frames inside the container are ordinary VP8 or
 * VP8L bitstreams, though, so the first one only needs re-wrapping in a still WebP
 * header to become something every decoder already understands.
 */
final class webpFrameExtractor {
	/** Bytes of frame header in front of an ANMF chunk's image data. */
	private const ANMF_HEADER_SIZE = 16;

	/** Alpha bit in a VP8X flags byte. */
	private const VP8X_ALPHA_FLAG = 0x10;

	/** Whether this file has to be unpacked before a decoder can read it. */
	public static function needsExtraction(string $sourceFile): bool {
		return imageProbe::detectType($sourceFile) === imageProbe::WEBP
			&& imageProbe::isAnimated($sourceFile);
	}

	/**
	 * Write the first frame to a temporary still WebP. The caller owns that file and is
	 * responsible for deleting it.
	 *
	 * Only the frame itself is read. The first ANMF sits near the front of the container,
	 * so a long animation costs no more memory than a short one.
	 *
	 * @return string|null Path to the temporary file, or null when the frame could not be read.
	 */
	public static function extractFirstFrame(string $sourceFile): ?string {
		$handle = @fopen($sourceFile, 'rb');

		if ($handle === false) {
			return null;
		}

		try {
			$header = (string)fread($handle, 12);

			if (strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
				return null;
			}

			$frame = self::readFirstFrame($handle, (int)(fstat($handle)['size'] ?? 0));

			if ($frame === null) {
				return null;
			}

			$still = self::buildStillWebp($frame);

			if ($still === null) {
				return null;
			}

			$destination = tempnam(sys_get_temp_dir(), 'kokowebp');

			if ($destination === false) {
				return null;
			}

			if (file_put_contents($destination, $still) === false) {
				@unlink($destination);

				return null;
			}

			return $destination;
		} finally {
			fclose($handle);
		}
	}

	/**
	 * Seek through the container to the first ANMF chunk and read that chunk alone.
	 *
	 * @param resource $handle
	 * @return array|null ['width' => int, 'height' => int, 'chunks' => [fourcc => payload]]
	 */
	private static function readFirstFrame($handle, int $fileSize): ?array {
		$offset = 12;

		while ($offset + 8 <= $fileSize) {
			if (fseek($handle, $offset) !== 0) {
				return null;
			}

			$chunkHeader = (string)fread($handle, 8);

			if (strlen($chunkHeader) < 8) {
				return null;
			}

			$fourcc = substr($chunkHeader, 0, 4);
			$size = unpack('V', substr($chunkHeader, 4, 4))[1];

			$offset += 8;

			// a chunk claiming to run past the end of the file cannot be trusted
			if ($size < 0 || $offset + $size > $fileSize) {
				return null;
			}

			if ($fourcc === 'ANMF') {
				if ($size <= self::ANMF_HEADER_SIZE) {
					return null;
				}

				$payload = (string)fread($handle, $size);

				return strlen($payload) === $size ? self::parseFrame($payload) : null;
			}

			// chunks are padded to an even length
			$offset += $size + ($size % 2);
		}

		return null;
	}

	/**
	 * Read an ANMF payload: a fixed frame header, then the same chunk layout a still
	 * WebP uses.
	 *
	 * @return array ['width' => int, 'height' => int, 'chunks' => [fourcc => payload]]
	 */
	private static function parseFrame(string $payload): array {
		$chunks = [];

		foreach (self::walkChunks($payload, self::ANMF_HEADER_SIZE) as [$fourcc, $chunkPayload]) {
			$chunks[$fourcc] = $chunkPayload;
		}

		return [
			// both are stored minus one, as 24 bit little endian values
			'width' => self::readUint24($payload, 6) + 1,
			'height' => self::readUint24($payload, 9) + 1,
			'chunks' => $chunks,
		];
	}

	/**
	 * Wrap the frame's bitstream in a still WebP header.
	 *
	 * @return string|null Null when the frame holds no bitstream we recognise.
	 */
	private static function buildStillWebp(array $frame): ?string {
		$chunks = $frame['chunks'];

		$body = '';

		// a frame carrying its alpha separately needs the extended header to declare it
		if (isset($chunks['ALPH'])) {
			$body .= self::chunk('VP8X', self::vp8xPayload($frame['width'], $frame['height']));
			$body .= self::chunk('ALPH', $chunks['ALPH']);
		}

		if (isset($chunks['VP8 '])) {
			$body .= self::chunk('VP8 ', $chunks['VP8 ']);
		} elseif (isset($chunks['VP8L'])) {
			$body .= self::chunk('VP8L', $chunks['VP8L']);
		} else {
			return null;
		}

		return 'RIFF' . pack('V', 4 + strlen($body)) . 'WEBP' . $body;
	}

	/** The ten byte VP8X payload: flags, three reserved bytes, then canvas size less one. */
	private static function vp8xPayload(int $width, int $height): string {
		return chr(self::VP8X_ALPHA_FLAG)
			. "\x00\x00\x00"
			. self::writeUint24($width - 1)
			. self::writeUint24($height - 1);
	}

	/** Emit one RIFF chunk, padded to an even length. */
	private static function chunk(string $fourcc, string $payload): string {
		$chunk = $fourcc . pack('V', strlen($payload)) . $payload;

		return (strlen($payload) % 2) === 1 ? $chunk . "\x00" : $chunk;
	}

	/**
	 * Walk a RIFF chunk sequence.
	 *
	 * @return Generator<array{0: string, 1: string}> Four character code, then payload.
	 */
	private static function walkChunks(string $data, int $offset): Generator {
		$length = strlen($data);

		while ($offset + 8 <= $length) {
			$fourcc = substr($data, $offset, 4);
			$size = unpack('V', substr($data, $offset + 4, 4))[1];

			$offset += 8;

			// a truncated chunk means the rest of the container cannot be trusted
			if ($size < 0 || $offset + $size > $length) {
				return;
			}

			yield [$fourcc, substr($data, $offset, $size)];

			// chunks are padded to an even length
			$offset += $size + ($size % 2);
		}
	}

	private static function readUint24(string $data, int $offset): int {
		return ord($data[$offset])
			| (ord($data[$offset + 1]) << 8)
			| (ord($data[$offset + 2]) << 16);
	}

	private static function writeUint24(int $value): string {
		return chr($value & 0xFF) . chr(($value >> 8) & 0xFF) . chr(($value >> 16) & 0xFF);
	}
}
