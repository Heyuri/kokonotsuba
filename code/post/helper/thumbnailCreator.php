<?php

class thumbnailCreator {
	// whether thumbnail creation is enabled at all
	private readonly bool $useThumb;

	// configuration for thumbnailing (method, quality, max sizes, etc.)
	private readonly array $thumbConfig;

	// the directory where thumbnail files will be stored
	private readonly string $thumbDirectory;

	/**
	 * Construct the thumbnailCreator with the required configuration.
	 *
	 * @param bool   $useThumb        Global enable/disable switch for thumbnailing
	 * @param array  $thumbConfig     Configuration array defining thumbnail method and settings
	 * @param string $thumbDirectory  Directory on disk where thumbnails are written
	 */
	public function __construct(bool $useThumb, array $thumbConfig, string $thumbDirectory) {
		$this->useThumb = $useThumb;
		$this->thumbConfig = $thumbConfig;
		$this->thumbDirectory = $thumbDirectory;
	}

	/**
	 * Create a thumbnail file and save it to the board's thumbnail directory.
	 *
	 * NOTE:
	 *   This function processes ONE file only.
	 *   When handling multiple uploaded files, call this method once per file.
	 *
	 * @param string $thumbnailPath            Path to the source image or media file
	 * @param string $thumbnailDestinationName Final filename for the generated thumbnail
	 * @param int    $imgW                     Width of the original image
	 * @param int    $imgH                     Height of the original image
	 * @param int    $thumbnailWidth           Desired thumbnail width
	 * @param int    $thumbnailHeight          Desired thumbnail height
	 */
	public function makeAndUpload(
		string $thumbnailPath,
		string $thumbnailDestinationName,
		int $imgW,
		int $imgH,
		int $thumbnailWidth,
		int $thumbnailHeight
	): void {

		// if thumbnailing is disabled via config, do nothing
		if(!$this->useThumb) return;

		// build the full destination path for the final thumbnail
		$thumbnailDestination = $this->thumbDirectory . $thumbnailDestinationName;

		// which thumbnail backend to use (GD, Imagick, ffmpeg wrapper, etc.)
		$thumbType = $this->thumbConfig['Method'];

		// load the backend-specific thumbnail generator
		require_once(getBackendCodeDir() . 'thumb/thumb.' . $thumbType . '.php');

		// create a wrapper object for generating the thumbnail
		// it receives the source file path and its original dimensions
		$thObj = new ThumbWrapper($thumbnailPath, $imgW, $imgH);

		// configure the thumbnail generator (sizes, quality, method-specific options)
		$thObj->setThumbnailConfig($thumbnailWidth, $thumbnailHeight, $this->thumbConfig);

		// create the thumbnail and write it directly to the destination file
		$thObj->makeThumbnailtoFile($thumbnailDestination);

		// adjust permissions so the system can read/serve the thumbnail
		chmod($thumbnailDestination, 0666);

		// cleanup thumbnail wrapper object
		unset($thObj);
	}
}
