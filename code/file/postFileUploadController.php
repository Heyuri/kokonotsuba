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

	public function __construct(array $config,
	 fileFromUpload $fileFromUpload,
	 thumbnailCreator $thumbnailCreator,
	 thumbnail $thumbnail,
	 string $boardImagePath,) {
		$this->config = $config;

		$this->fileFromUpload = $fileFromUpload;
		$this->thumbnailCreator = $thumbnailCreator;

		$this->file = $fileFromUpload->getFile();
		$this->thumbnail = $thumbnail;

		$this->boardImagePath = $boardImagePath;
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

		// get file index
		$index = $this->file->getIndex();

		// assemble stored file name
		$storedFileName = $timeInMilliseconds . '_' . $index;

		$thumbnailDestinationName = $storedFileName . 's.' . $thumbnailExtention;

		$thumbnailPath = $this->thumbnail->getThumbnailFileName();

		// If the thumbnail size is 0, either being an error or intentionally invalidated (i,e a shockwave flash file)
		if($thumbnailWidth === 0 || $thumbnailHeight === 0) {
			return;
		}
		
		// generate image thumbnail
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

		// Build thumbnail output path
		$formatSuffix = $this->config['THUMB_SETTING']['Format'];
		$thumbnailPath = $videoPath . '_thumbnail.' . $formatSuffix;

		// get the halfway timestamp of the video
		$medianDuration = getMedianTime($videoPath);

		// Generate the thumbnail from video from the frame in the middle of the video
		createVideoThumbnail($videoPath, $thumbnailPath, $medianDuration);

		// Update the thumbnail file reference
		$this->thumbnail->setThumbnailFileName($thumbnailPath);
	}
	

	private function validateThumbnail(string $fileExtention, string $mimeType): void {
		// create video extensions array
		$videoExts = explode('|', strtolower($this->config['VIDEO_EXT']));
		
		// generate thumbnail for video
		if(isVideo($mimeType) && in_array($fileExtention, $videoExts)) {
			$this->generateVideoThumbnail($this->file);
		}
		// swf cannot have a thumbnail, so invalidate
		if ($fileExtention === 'swf') {
			$this->thumbnail->setThumbnailWidth(0);
			$this->thumbnail->setThumbnailHeight(0);
		}
	}


	private function validateFileExtentionAndMimeType(string $fileExtention, string $mimeType): void {
		$ext = strtolower(ltrim($fileExtention, '.'));

		// Check if the extension is allowed
		if (!array_key_exists($ext, $this->config['ALLOW_UPLOAD_EXT'])) {
			throw new BoardException(_T('regist_upload_notsupport'));
		}

		// Check if the MIME type matches the one configured for the extension
		if ($this->config['ALLOW_UPLOAD_EXT'][$ext] !== $mimeType) {
			throw new BoardException(_T('regist_upload_notsupport'));
		}
	}

	private function validateFileSize(int $fileSize): void {
		if ($fileSize > $this->config['MAX_KB'] * 1024) {
			throw new BoardException(_T('regist_upload_exceedcustom'));
		}
	}

	private function validateFileHash(string $md5Hash) {
		if (in_array($md5Hash, $this->config['BAD_FILEMD5'])) {
			throw new BoardException(_T('regist_upload_blocked'));
		}
	}

	private function validateFileUploadStatus(int $upfileStatus): void {
		switch($upfileStatus){
    	    case UPLOAD_ERR_OK:
    	        break;
    	    case UPLOAD_ERR_FORM_SIZE:
    	        throw new BoardException('ERROR: The file is too large.(upfile)');
    	        break;
    	    case UPLOAD_ERR_INI_SIZE:
    	        throw new BoardException('ERROR: The file is too large.(php.ini)');
    	        break;
    	    case UPLOAD_ERR_PARTIAL:
            	throw new BoardException('ERROR: The uploaded file was only partially uploaded.');
    	        break;
    	    case UPLOAD_ERR_NO_TMP_DIR:
    	        throw new BoardException('ERROR: Missing a temporary folder.');
    	        break;
    	    case UPLOAD_ERR_CANT_WRITE:
    	        throw new BoardException('ERROR: Failed to write file to disk.');
    	        break;
    	    default:
    	        throw new BoardException('ERROR: Unable to save the uploaded file.');
    	}
	}
	
}