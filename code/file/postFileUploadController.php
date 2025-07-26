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

	// error handler
	private softErrorHandler $softErrorHandler;

	public function __construct(array $config,
	 fileFromUpload $fileFromUpload,
	 thumbnailCreator $thumbnailCreator,
	 thumbnail $thumbnail,
	 string $boardImagePath,
	 softErrorHandler $softErrorHandler) {
		$this->config = $config;

		$this->fileFromUpload = $fileFromUpload;
		$this->thumbnailCreator = $thumbnailCreator;

		$this->file = $fileFromUpload->getFile();
		$this->thumbnail = $thumbnail;

		$this->boardImagePath = $boardImagePath;
		
		$this->softErrorHandler = $softErrorHandler;
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

		// If the thumbnail size is 0, either being an error or intentionally invalidated (i,e a shockwave flash file)
		if($thumbnailWidth === 0 || $thumbnailHeight === 0) {
			return;
		}

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
		$md5Hash = $this->file->getMd5Chksum();
		$fileExtention = $this->file->getExtention();
		$fileSize = $this->file->getFileSize();
		$mimeType = $this->file->getMimeType();
		$upfileStatus = $this->file->getFileStatus();

		$this->validateFileHash($md5Hash);
		$this->validateFileSize($fileSize);
		$this->validateFileExtentionAndMimeType($fileExtention, $mimeType);
		$this->validateFileUploadStatus($upfileStatus);
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


	private function validateFileExtentionAndMimeType(string $fileExtention, string $mimeType): void {
		$ext = strtolower(ltrim($fileExtention, '.'));

		// Check if the extension is allowed
		if (!array_key_exists($ext, $this->config['ALLOW_UPLOAD_EXT'])) {
			$this->softErrorHandler->errorAndExit(_T('regist_upload_notsupport'));
		}

		// Check if the MIME type matches the one configured for the extension
		if ($this->config['ALLOW_UPLOAD_EXT'][$ext] !== $mimeType) {
			$this->softErrorHandler->errorAndExit(_T('regist_upload_notsupport'));
		}
	}

	private function validateFileSize(int $fileSize): void {
		if ($fileSize > $this->config['MAX_KB'] * 1024) {
			$this->softErrorHandler->errorAndExit(_T('regist_upload_exceedcustom'));
		}
	}

	private function validateFileHash(string $md5Hash) {
		if (in_array($md5Hash, $this->config['BAD_FILEMD5'])) {
			$this->softErrorHandler->errorAndExit(_T('regist_upload_blocked'));
		}
	}

	private function validateFileUploadStatus(int $upfileStatus): void {
		switch($upfileStatus){
    	    case UPLOAD_ERR_OK:
    	        break;
    	    case UPLOAD_ERR_FORM_SIZE:
    	        $this->softErrorHandler->errorAndExit('ERROR: The file is too large.(upfile)');
    	        break;
    	    case UPLOAD_ERR_INI_SIZE:
    	        $this->softErrorHandler->errorAndExit('ERROR: The file is too large.(php.ini)');
    	        break;
    	    case UPLOAD_ERR_PARTIAL:
            	$this->softErrorHandler->errorAndExit('ERROR: The uploaded file was only partially uploaded.');
    	        break;
    	    case UPLOAD_ERR_NO_TMP_DIR:
    	        $this->softErrorHandler->errorAndExit('ERROR: Missing a temporary folder.');
    	        break;
    	    case UPLOAD_ERR_CANT_WRITE:
    	        $this->softErrorHandler->errorAndExit('ERROR: Failed to write file to disk.');
    	        break;
    	    default:
    	        $this->softErrorHandler->errorAndExit('ERROR: Unable to save the uploaded file.');
    	}
	}
	
}