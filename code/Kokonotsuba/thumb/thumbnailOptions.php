<?php

namespace Kokonotsuba\thumb;

/**
 * Output settings for a single thumbnail, normalised out of a board's THUMB_SETTING.
 */
final class thumbnailOptions {
	/** Output formats a backend may be asked for. */
	public const FORMATS = ['jpg', 'png', 'gif', 'webp'];

	/** Used when the configured background colour is missing or malformed. */
	public const DEFAULT_BACKGROUND = 'f0e0d6';

	public function __construct(
		public readonly int $width,
		public readonly int $height,
		public readonly string $format = 'jpg',
		public readonly int $quality = 75,
		public readonly string $backgroundColour = '#' . self::DEFAULT_BACKGROUND,
	) {}

	/**
	 * Build options from a board's THUMB_SETTING array.
	 *
	 * @param array $thumbSetting Format / Quality / TransparentBackgroundColor
	 */
	public static function fromBoardConfig(int $width, int $height, array $thumbSetting): self {
		$format = strtolower(trim((string)($thumbSetting['Format'] ?? 'jpg')));

		if ($format === 'jpeg') {
			$format = 'jpg';
		}

		if (!in_array($format, self::FORMATS, true)) {
			$format = 'jpg';
		}

		$quality = max(1, min(100, (int)($thumbSetting['Quality'] ?? 75)));

		$background = (string)($thumbSetting['TransparentBackgroundColor'] ?? '');

		return new self(
			max(1, $width),
			max(1, $height),
			$format,
			$quality,
			$background !== '' ? $background : '#' . self::DEFAULT_BACKGROUND
		);
	}

	/**
	 * Background colour as [red, green, blue], falling back to the default on a
	 * value we cannot parse.
	 *
	 * @return int[]
	 */
	public function backgroundRgb(): array {
		$hex = $this->normalisedBackgroundHex();

		return [
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2)),
		];
	}

	/** Background colour as a bare six digit hex string, without the leading hash. */
	public function normalisedBackgroundHex(): string {
		$hex = strtolower(ltrim(trim($this->backgroundColour), '#'));

		// expand the shorthand form, "fff" meaning "ffffff"
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
			return self::DEFAULT_BACKGROUND;
		}

		return $hex;
	}

	/**
	 * Whether the output format carries an alpha channel. GIF is treated as opaque:
	 * its single transparent index survives resampling badly, so those thumbnails
	 * get the background colour instead.
	 */
	public function keepsAlpha(): bool {
		return $this->format === 'png' || $this->format === 'webp';
	}
}
