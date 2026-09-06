<?php

namespace Kokonotsuba\ban;

/**
 * The banhammer image a ban notice is stamped with.
 *
 * A drop-in directory, the way the banner module takes banners: whatever sits in
 * static/image/ban/ is drawn from at random, so a notice is not always the same picture. One
 * file in there is a rotation of one, which is still the rotation. An install with no such
 * directory keeps the single hammer.gif it has always used.
 *
 * Each image is measured off disk so the tag can carry width and height; see {@see banImage}.
 */
class banImagePicker {
	private const DIRECTORY = 'image/ban';
	private const FALLBACK = 'image/hammer.gif';
	private const EXTENSIONS = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'avif'];

	/** Measured sizes by relative path, so one render never stats the same file twice. */
	private array $sizeCache = [];

	public function __construct(
		private string $staticPath,
		private string $staticUrl
	) {}

	/** One ban image, drawn again on every call. */
	public function random(): banImage {
		$files = $this->listImages();

		if ($files === []) {
			return $this->imageFor(self::FALLBACK);
		}

		return $this->imageFor(self::DIRECTORY . '/' . $files[random_int(0, count($files) - 1)]);
	}

	/**
	 * A named image below the static root, measured the same way.
	 *
	 * @param string $relativePath e.g. 'image/notbanned.png'.
	 */
	public function imageFor(string $relativePath): banImage {
		[$width, $height] = $this->measure($relativePath);

		return new banImage($this->toUrl($relativePath), $width, $height);
	}

	/**
	 * Read a file's pixel size.
	 *
	 * getimagesize() reads the header rather than the picture, and anything it cannot make sense
	 * of - a missing file, a format it does not know - comes back as no size at all rather than
	 * an error, because a ban page is not worth failing over a picture.
	 *
	 * @return array{0: int, 1: int}
	 */
	private function measure(string $relativePath): array {
		if (isset($this->sizeCache[$relativePath])) {
			return $this->sizeCache[$relativePath];
		}

		$path = rtrim($this->staticPath, '/\\') . '/' . $relativePath;
		$size = is_file($path) ? @getimagesize($path) : false;

		return $this->sizeCache[$relativePath] = $size === false
			? [0, 0]
			: [(int) $size[0], (int) $size[1]];
	}

	/** @return list<string> File names in the ban image directory, images only. */
	private function listImages(): array {
		$directory = rtrim($this->staticPath, '/\\') . '/' . self::DIRECTORY;

		if (!is_dir($directory)) {
			return [];
		}

		$files = [];

		foreach (scandir($directory) ?: [] as $file) {
			if ($file === '.' || $file === '..' || !is_file($directory . '/' . $file)) {
				continue;
			}

			if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
				$files[] = $file;
			}
		}

		return $files;
	}

	/** A file name is whatever the moderator uploaded, so each segment is encoded for the URL. */
	private function toUrl(string $relativePath): string {
		$segments = array_map('rawurlencode', explode('/', $relativePath));

		return rtrim($this->staticUrl, '/') . '/' . implode('/', $segments);
	}
}
