<?php

class thumbnailCreator {
    private readonly bool $useThumb;
	private readonly array $thumbConfig;
	private readonly string $thumbDirectory;

	public function __construct(bool $useThumb, array $thumbConfig, string $thumbDirectory) {
		$this->useThumb = $useThumb;
		$this->thumbConfig = $thumbConfig;
		$this->thumbDirectory = $thumbDirectory;
	}

	public function makeAndUpload(string $thumbnailPath, string $thumbnailDestinationName, int $imgW, int $imgH, int $thumbnailWidth, int $thumbnailHeight): void {
		//if (!$dest || !is_file($dest)) return;
		if(!$this->useThumb) return;

		$thumbnailDestination = $this->thumbDirectory . $thumbnailDestinationName;

		$thumbType = $this->thumbConfig['Method'];

		require(getBackendCodeDir() . 'thumb/thumb.' . $thumbType . '.php');

		$thObj = new ThumbWrapper($thumbnailPath, $imgW, $imgH);

		$thObj->setThumbnailConfig($thumbnailWidth, $thumbnailHeight, $this->thumbConfig);
		$thObj->makeThumbnailtoFile($thumbnailDestination);
		chmod($thumbnailDestination, 0666);
		unset($thObj);
	}
}
