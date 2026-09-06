<?php

namespace Kokonotsuba\post\helper;

use Kokonotsuba\thumb\thumbnailerFactory;
use Kokonotsuba\thumb\thumbnailOptions;
use Kokonotsuba\thumb\webpFrameExtractor;

class thumbnailCreator {
	// whether thumbnail creation is enabled at all
	private readonly bool $useThumb;

	// configuration for thumbnailing (method, format, quality, background)
	private readonly array $thumbConfig;

	// the directory where thumbnail files will be stored
	private readonly string $thumbDirectory;

	/**
	 * @param bool   $useThumb       Global enable/disable switch for thumbnailing
	 * @param array  $thumbConfig    The board's THUMB_SETTING
	 * @param string $thumbDirectory Directory on disk where thumbnails are written
	 */
	public function __construct(bool $useThumb, array $thumbConfig, string $thumbDirectory) {
		$this->useThumb = $useThumb;
		$this->thumbConfig = $thumbConfig;
		$this->thumbDirectory = $thumbDirectory;
	}

	/**
	 * Thumbnail one file into the board's thumbnail directory. Call once per uploaded file.
	 *
	 * @param string $sourcePath      Path to the source image, or to a frame already pulled from a video
	 * @param string $destinationName Filename for the generated thumbnail
	 * @param int    $thumbnailWidth  Desired thumbnail width
	 * @param int    $thumbnailHeight Desired thumbnail height
	 * @return bool True when a thumbnail was written.
	 */
	public function makeAndUpload(
		string $sourcePath,
		string $destinationName,
		int $thumbnailWidth,
		int $thumbnailHeight
	): bool {
		if (!$this->useThumb) {
			return false;
		}

		$destination = $this->thumbDirectory . $destinationName;

		// an animated WebP has to be unpacked into a still one first, since no decoder we
		// have can read the animated form
		$unpackedFrame = null;

		if (webpFrameExtractor::needsExtraction($sourcePath)) {
			$unpackedFrame = webpFrameExtractor::extractFirstFrame($sourcePath);

			if ($unpackedFrame === null) {
				return false;
			}

			$sourcePath = $unpackedFrame;
		}

		try {
			// the factory falls back to the other backend when the preferred one cannot read
			// this particular file
			$factory = new thumbnailerFactory((string)($this->thumbConfig['Method'] ?? 'gd'));
			$thumbnailer = $factory->forSource($sourcePath);

			if ($thumbnailer === null) {
				return false;
			}

			$options = thumbnailOptions::fromBoardConfig($thumbnailWidth, $thumbnailHeight, $this->thumbConfig);

			if (!$thumbnailer->makeThumbnail($sourcePath, $destination, $options)) {
				return false;
			}
		} finally {
			if ($unpackedFrame !== null) {
				@unlink($unpackedFrame);
			}
		}

		// permissions so the web server can serve it
		@chmod($destination, 0666);

		return true;
	}
}
