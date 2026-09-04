<?php

namespace Kokonotsuba\thumb;

use GdImage;

/**
 * Thumbnails through the GD extension.
 *
 * GD decodes one still frame, which is all a thumbnail needs, but it cannot open an
 * animated WebP at all and it holds the whole decoded frame in memory. Both cases are
 * declined in supports() so thumbnailerFactory can hand them to ffmpeg instead.
 */
final class gdThumbnailer implements thumbnailerInterface {
	/**
	 * Sources above this pixel count go to ffmpeg. GD would need roughly four bytes of
	 * memory per pixel, so a decompression bomb takes the request down with it.
	 */
	private const MAX_SOURCE_PIXELS = 40000000;

	/** zlib level for PNG output. PNG is lossless, so Quality has nothing to say about it. */
	private const PNG_COMPRESSION = 6;

	/** Container type to the GD function that reads it. */
	private const LOADERS = [
		imageProbe::JPEG => 'imagecreatefromjpeg',
		imageProbe::PNG => 'imagecreatefrompng',
		imageProbe::GIF => 'imagecreatefromgif',
		imageProbe::WEBP => 'imagecreatefromwebp',
		imageProbe::BMP => 'imagecreatefrombmp',
		imageProbe::AVIF => 'imagecreatefromavif',
	];

	public function getMethodName(): string {
		return 'gd';
	}

	public function isWorking(): bool {
		return extension_loaded('gd')
			&& function_exists('imagecreatetruecolor')
			&& function_exists('imagecopyresampled');
	}

	public function describe(): string {
		if (!$this->isWorking()) {
			return 'GD';
		}

		$info = gd_info();

		return trim('GD ' . ($info['GD Version'] ?? ''));
	}

	public function supports(string $sourceFile): bool {
		if (!$this->isWorking()) {
			return false;
		}

		$type = imageProbe::detectType($sourceFile);

		if ($this->loaderFor($type) === null) {
			return false;
		}

		// GD's WebP reader rejects the animated form outright
		if ($type === imageProbe::WEBP && imageProbe::isAnimated($sourceFile)) {
			return false;
		}

		$size = @getimagesize($sourceFile);

		// unreadable dimensions mean GD is unlikely to get further than we did
		if ($size === false || $size[0] < 1 || $size[1] < 1) {
			return false;
		}

		return ($size[0] * $size[1]) <= self::MAX_SOURCE_PIXELS;
	}

	public function makeThumbnail(string $sourceFile, string $destinationFile, thumbnailOptions $options): bool {
		if (!$this->supports($sourceFile)) {
			return false;
		}

		$source = $this->load($sourceFile);

		if ($source === null) {
			return false;
		}

		$source = $this->applyOrientation($source, imageProbe::orientation($sourceFile));

		$thumbnail = imagecreatetruecolor($options->width, $options->height);

		if ($thumbnail === false) {
			imagedestroy($source);

			return false;
		}

		$this->prepareCanvas($thumbnail, $options);

		imagecopyresampled(
			$thumbnail,
			$source,
			0, 0,
			0, 0,
			$options->width, $options->height,
			imagesx($source), imagesy($source)
		);

		$written = $this->write($thumbnail, $destinationFile, $options);

		imagedestroy($source);
		imagedestroy($thumbnail);

		return $written;
	}

	/** The GD reader for a container type, or null when GD was built without it. */
	private function loaderFor(string $type): ?string {
		$loader = self::LOADERS[$type] ?? null;

		return ($loader !== null && function_exists($loader)) ? $loader : null;
	}

	/** Decode the source file, or null if GD refuses it despite the format check. */
	private function load(string $sourceFile): ?GdImage {
		$loader = $this->loaderFor(imageProbe::detectType($sourceFile));

		if ($loader === null) {
			return null;
		}

		$image = @$loader($sourceFile);

		return $image instanceof GdImage ? $image : null;
	}

	/**
	 * Fill the canvas so the resample lands on the right ground: transparent for formats
	 * that keep an alpha channel, the configured background colour otherwise.
	 */
	private function prepareCanvas(GdImage $thumbnail, thumbnailOptions $options): void {
		if ($options->keepsAlpha()) {
			// blending stays off so the source's own alpha is copied rather than composited
			imagealphablending($thumbnail, false);
			imagesavealpha($thumbnail, true);
			imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));

			return;
		}

		[$red, $green, $blue] = $options->backgroundRgb();

		// blending on, so a transparent source composites over the background instead of
		// punching black holes in it
		imagealphablending($thumbnail, true);
		imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, $red, $green, $blue));
	}

	/** Encode the finished canvas in the configured format. */
	private function write(GdImage $thumbnail, string $destinationFile, thumbnailOptions $options): bool {
		return match ($options->format) {
			'png' => imagepng($thumbnail, $destinationFile, self::PNG_COMPRESSION),
			'gif' => imagegif($thumbnail, $destinationFile),
			'webp' => function_exists('imagewebp')
				&& imagewebp($thumbnail, $destinationFile, $options->quality),
			default => imagejpeg($thumbnail, $destinationFile, $options->quality),
		};
	}

	/**
	 * Rotate or mirror a decoded image to match its EXIF orientation. The original is
	 * destroyed when a rotation replaces it.
	 */
	private function applyOrientation(GdImage $image, int $orientation): GdImage {
		if ($orientation === 1) {
			return $image;
		}

		// keep any alpha through the transform rather than flattening it to black
		imagealphablending($image, false);
		imagesavealpha($image, true);

		// the four mirrored orientations are flipped first, then share a rotation with their pair
		$flip = match ($orientation) {
			2, 7 => IMG_FLIP_HORIZONTAL,
			4, 5 => IMG_FLIP_VERTICAL,
			default => null,
		};

		if ($flip !== null) {
			imageflip($image, $flip);
		}

		$angle = match ($orientation) {
			3 => 180,
			5, 6, 7 => -90,
			8 => 90,
			default => 0,
		};

		if ($angle === 0) {
			return $image;
		}

		$rotated = imagerotate($image, $angle, imagecolorallocatealpha($image, 0, 0, 0, 127));

		if ($rotated === false) {
			return $image;
		}

		imagesavealpha($rotated, true);
		imagedestroy($image);

		return $rotated;
	}
}
