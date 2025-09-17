<?php

class attachment {
	public function __construct(
		private readonly fileEntry $fileEntry,
		private readonly board $board
	) {}

	public function getPath(): string {
		// whether the file is hidden or not
		$isHidden = $this->fileEntry->isHidden();

		// if its hidden then adjust paths / dirs accordingly
		if($isHidden) {
			// set the upload directory to  be the `global/hidden/` directory
			$path = $this->getHiddenPath();
		} else {
			// full directory for the uploaded file
			$path= $this->getUploadPath();
		}

		// return path
		return $path;
	}

	public function getHiddenPath(): string {
		// the directory for hidden attachments
		$hiddenDirectory = $this->getHiddenDirectory();

		// generate the hidden attachment path for the attachment
		$hiddenPath = $this->getBasePath($hiddenDirectory);

		// return path
		return $hiddenPath;
	}

	private function getHiddenDirectory(): string {
		// get the hidden directory
		$hiddenDirectory = getGlobalAttachmentDirectory();

		// return hidden directory
		return $hiddenDirectory;
	}

	public function getUploadPath(): string {
		// get the upload directory
		$uploadDirectory = $this->getUploadDirectory();

		// generate the upload path
		$uploadPath = $this->getBasePath($uploadDirectory);

		// return path
		return $uploadPath;
	}

	public function getUploadDirectory(): string {
		// postfix dir
		$postfixDirectory = $this->generatePostfixDirectory();

		// get the base path for uploaded files
		$baseDirectory = $this->board->getBoardUploadedFilesDirectory();

		// get the base path for uploaded files
		$uploadDirectory = $baseDirectory . $postfixDirectory;

		// return upload dir
		return $uploadDirectory;
	}

	private function generatePostfixDirectory(): string {
		// relative image directory
		$imageDirectory = $this->board->getConfigValue('IMG_DIR');

		// relative thumbnail directory
		$thumbnailDirectory = $this->board->getConfigValue('THUMB_DIR');

		// wheather its a thumbnail or not
		$isThumb = $this->fileEntry->isThumb();

		// postfix directory
		if($isThumb) {
			// thumbnail directory postfix
			$postfixDirectory = $thumbnailDirectory; 
		} else {
			// image directory postfix
			$postfixDirectory = $imageDirectory;
		}

		// return dir
		return $postfixDirectory;
	}

	private function getBasePath(string $uploadDirectory): string {
		// file stored name
		$storedFileName = $this->fileEntry->getStoredFileName();

		// file extension
		$fileExtension = $this->fileEntry->getFileExt();

		// build the full path
		$filePath = $uploadDirectory . $storedFileName . '.' . $fileExtension;

		// return result
		return $filePath;
	}

	public function isHidden(): bool {
		// get the value is_hidden from fileEntry
		$isHidden = $this->fileEntry->isHidden();

		// return result
		return $isHidden;
	}

	public function getBoardUid(): int {
		// get the value board_uid from fileEntry
		$boardUid = $this->fileEntry->getBoardUid();

		// return result
		return $boardUid;
	}

	public function getFileId(): int {
		// get the id value from fileEntry
		$fileId = $this->fileEntry->getId();

		// return result
		return $fileId;
	}
}