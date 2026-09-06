<?php

namespace Kokonotsuba\thumb;

/**
 * Thumbnails through the ffmpeg binary.
 *
 * ffmpeg decodes the first frame of nearly anything we accept as an upload, which makes
 * it the backend for what GD cannot open: AVIF, and stills too large to decode in memory.
 * It scales in a stream, so source size costs it nothing.
 */
final class ffmpegThumbnailer implements thumbnailerInterface {
	private const BINARY = 'ffmpeg';

	/** mjpeg's quality scale runs the other way to ours: 2 is best, 31 is worst. */
	private const MJPEG_BEST = 2;
	private const MJPEG_WORST = 31;

	public function getMethodName(): string {
		return 'ffmpeg';
	}

	public function isWorking(): bool {
		return $this->installedVersion() !== null;
	}

	public function describe(): string {
		$version = $this->installedVersion();

		if ($version === null) {
			return 'ffmpeg';
		}

		return trim('ffmpeg ' . $version);
	}

	public function supports(string $sourceFile): bool {
		if (!$this->isWorking() || !is_readable($sourceFile)) {
			return false;
		}

		// ffmpeg's WebP decoder only handles the still form, same as GD's; an animated one
		// has to be unpacked by webpFrameExtractor before either can see it
		return !(imageProbe::detectType($sourceFile) === imageProbe::WEBP
			&& imageProbe::isAnimated($sourceFile));
	}

	public function makeThumbnail(string $sourceFile, string $destinationFile, thumbnailOptions $options): bool {
		if (!$this->supports($sourceFile)) {
			return false;
		}

		$command = self::BINARY
			. ' -y -v error -nostdin'
			. ' -i ' . escapeshellarg($sourceFile)
			. ' -filter_complex ' . escapeshellarg($this->buildFilter($options, imageProbe::orientation($sourceFile)))
			. ' -map ' . escapeshellarg('[out]')
			. ' -frames:v 1 -an -sn'
			. ' ' . $this->encoderArguments($options)
			. ' -f image2 ' . escapeshellarg($destinationFile)
			. ' 2>&1';

		@exec($command, $output, $status);

		return $status === 0 && is_file($destinationFile) && filesize($destinationFile) > 0;
	}

	/**
	 * Version string of the installed binary, or null when it is missing or unusable.
	 * Probing shells out, so the answer is kept for the rest of the request.
	 */
	private function installedVersion(): ?string {
		static $version = false;

		if ($version !== false) {
			return $version;
		}

		$version = null;

		if (!function_exists('exec')) {
			return $version;
		}

		@exec(self::BINARY . ' -version 2>&1', $output, $status);

		if ($status !== 0) {
			return $version;
		}

		// "ffmpeg version 6.1.1-3 Copyright (c) ..."
		$version = preg_match('/^ffmpeg version (\S+)/', $output[0] ?? '', $matches) ? $matches[1] : '';

		return $version;
	}

	/**
	 * Build the filter graph. Scaling always produces the exact requested box, matching
	 * what the GD backend does with the dimensions worked out upstream.
	 *
	 * @param int $orientation EXIF orientation of the source, 1 through 8.
	 */
	private function buildFilter(thumbnailOptions $options, int $orientation): string {
		$steps = [];

		$rotation = $this->orientationFilter($orientation);

		if ($rotation !== '') {
			$steps[] = $rotation;
		}

		$steps[] = sprintf('scale=%d:%d:flags=lanczos', $options->width, $options->height);
		$steps[] = 'format=rgba';

		$scaled = '[0:v]' . implode(',', $steps) . '[fg]';

		$pixelFormat = match ($options->format) {
			'png', 'webp' => 'rgba',
			'jpg' => 'yuvj420p',
			// the GIF encoder wants a palette, which ffmpeg builds for us if we leave it alone
			default => '',
		};

		if ($options->keepsAlpha()) {
			return $scaled . ';[fg]format=' . $pixelFormat . '[out]';
		}

		// lay the frame over the configured background so transparency does not come out black
		$background = sprintf(
			'color=c=0x%s:s=%dx%d[bg]',
			$options->normalisedBackgroundHex(),
			$options->width,
			$options->height
		);

		$flatten = '[bg][fg]overlay=shortest=1';

		if ($pixelFormat !== '') {
			$flatten .= ',format=' . $pixelFormat;
		}

		return $scaled . ';' . $background . ';' . $flatten . '[out]';
	}

	/** ffmpeg filter that corrects an EXIF orientation, or an empty string for none. */
	private function orientationFilter(int $orientation): string {
		return match ($orientation) {
			2 => 'hflip',
			3 => 'hflip,vflip',
			4 => 'vflip',
			5 => 'transpose=0',
			6 => 'transpose=1',
			7 => 'transpose=3',
			8 => 'transpose=2',
			default => '',
		};
	}

	/** Encoder and quality arguments for the configured output format. */
	private function encoderArguments(thumbnailOptions $options): string {
		return match ($options->format) {
			'png' => '-c:v png -compression_level 6',
			'gif' => '-c:v gif',
			'webp' => '-c:v libwebp -quality ' . $options->quality,
			default => '-c:v mjpeg -q:v ' . $this->mjpegScale($options->quality),
		};
	}

	/** Map our 1-100 quality onto mjpeg's inverted 2-31 scale. */
	private function mjpegScale(int $quality): int {
		$span = self::MJPEG_WORST - self::MJPEG_BEST;
		$scaled = self::MJPEG_WORST - (int)round($quality / 100 * $span);

		return max(self::MJPEG_BEST, min(self::MJPEG_WORST, $scaled));
	}
}
