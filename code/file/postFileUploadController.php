<?php
/*
* File upload controller for Kokonotsuba!
* handle file uploading for a post - depends on board and config
*/

class postFileUploadController {
	// config
	private readonly array $config;

	// board paths
	private readonly string $boardImagePath;

	// file handlers
	private readonly fileFromUpload $fileFromUpload;
	private readonly thumbnailCreator $thumbnailCreator;

	// files
	private file $file;
	private thumbnail $thumbnail;

	private readonly globalHTML $globalHTML;


	public function __construct(array $config,
	 fileFromUpload $fileFromUpload,
	 thumbnailCreator $thumbnailCreator,
	 thumbnail $thumbnail,
	 globalHTML $globalHTML,
	 string $boardImagePath) {
		$this->config = $config;

		$this->fileFromUpload = $fileFromUpload;
		$this->thumbnailCreator = $thumbnailCreator;

		$this->file = $fileFromUpload->getFile();
		$this->thumbnail = $thumbnail;

		$this->boardImagePath = $boardImagePath;
		
		$this->globalHTML = $globalHTML;
	}

	public function savePostFileToBoard(): void {
		$this->fileFromUpload->saveFile($this->boardImagePath);
	}

	public function savePostThumbnailToBoard(): void {
		$timeInMilliseconds = $this->file->getTimeInMilliseconds();
		
		$imageWidth = $this->file->getImageWidth();
		$imageHeight = $this->file->getImageHeight();

		$thumbnailExtention = $this->config['THUMB_SETTING']['Format'];
		$thumbnailWidth = $this->thumbnail->getThumbnailWidth();
		$thumbnailHeight = $this->thumbnail->getThumbnailHeight(); 

		$thumbnailDestinationName = $timeInMilliseconds . 's.' . $thumbnailExtention;

		$thumbnailPath = $this->thumbnail->getThumbnailFileName();

		$this->thumbnailCreator->makeAndUpload(
			$thumbnailPath,
			$thumbnailDestinationName,
			$imageWidth,
			$imageHeight,
			$thumbnailWidth,
			$thumbnailHeight
		);
	}

	public function validateFile(): void {
		$fileTemporaryName = $this->file->getTemporaryFileName();
		$md5Hash = $this->file->getMd5Chksum();
		$fileExtention = $this->file->getExtention();
		$fileSize = $this->file->getFileSize();
		$mimeType = $this->file->getMimeType();

		removeExifIfJpeg($fileTemporaryName);
		$this->validateFileHash($md5Hash);
		$this->validateFileSize($fileSize);
		$this->validateFileExtention($fileExtention);
		$this->validateThumbnail($fileExtention, $mimeType);
	}

	private function generateVideoThumbnail(file $file): void {
		// Get original temporary file path and extension
		$videoPath = $file->getTemporaryFileName();
		$videoExtention = substr($file->getExtention(), 1);

		// Build thumbnail output path
		$formatSuffix = $this->config['THUMB_SETTING']['Format'];
		$thumbnailPath = $videoPath . '_thumbnail.' . $formatSuffix;

		// Generate the thumbnail from video
		createVideoThumbnail($videoPath, $thumbnailPath, $videoExtention);

		// Update the thumbnail file reference
		$this->thumbnail->setThumbnailFileName($thumbnailPath);
	}
	

	private function validateThumbnail(string $fileExtention, string $mimeType): void {
		$videoExts = explode('|', strtolower($this->config['VIDEO_EXT']));
		
		// swf cannot have a thumbnail, so invalidate
		if ($fileExtention === '.swf') {
			$this->thumbnail->setThumbnailWidth(0);
			$this->thumbnail->setThumbnailHeight(0);
		}

		// generate thumbnail for video
		if(isVideo($mimeType) && in_array(substr($fileExtention, 1), $videoExts)) {
			$this->generateVideoThumbnail($this->file);
		}
	}

	private function validateFileExtention(string $fileExtention): void {
		$allowed = explode('|', strtolower($this->config['ALLOW_UPLOAD_EXT']));
		if (!in_array(substr($fileExtention, 1), $allowed)) {
			$this->globalHTML->error(_T('regist_upload_notsupport'));
		}
	}

	private function validateFileSize(int $fileSize): void {
		if ($fileSize > $this->config['MAX_KB'] * 1024) {
			$this->globalHTML->error(_T('regist_upload_exceedcustom'));
		}
	}

	private function validateFileHash(string $md5Hash) {
		if (in_array($md5Hash, $this->config['BAD_FILEMD5'])) {
			$this->globalHTML->error(_T('regist_upload_blocked'));
		}
	}
	
}