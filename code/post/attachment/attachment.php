<?php

class attachment {
	public function __construct(
		private readonly fileEntry $fileEntry,
		private readonly board $board
	) {}

	public function getPath(bool $isThumb = false): string {
		// whether the file is hidden or not
		$isHidden = $this->fileEntry->isHidden();

		// if its hidden then adjust paths / dirs accordingly
		if($isHidden) {
			// set the upload directory to  be the `global/hidden/` directory
			$path = $this->getHiddenPath($isThumb);
		} else {
			// full directory for the uploaded file
			$path= $this->getUploadPath($isThumb);
		}

		// return path
		return $path;
	}

	public function getHiddenPath(bool $isThumb = false): string {
		// the directory for hidden attachments
		$hiddenDirectory = $this->getHiddenDirectory();

		// generate the hidden attachment path for the attachment
		$hiddenPath = $this->generateFullPath($hiddenDirectory, $isThumb);

		// return path
		return $hiddenPath;
	}

	private function getHiddenDirectory(): string {
		// get the hidden directory
		$hiddenDirectory = getGlobalAttachmentDirectory();

		// return hidden directory
		return $hiddenDirectory;
	}

	public function getUploadPath(bool $isThumb = false): string {
		// get the upload directory
		$uploadDirectory = $this->getUploadDirectory($isThumb);

		// generate the upload path
		$uploadPath = $this->generateFullPath($uploadDirectory, $isThumb);

		// return path
		return $uploadPath;
	}

	public function getUploadDirectory(bool $isThumb = false): string {
		// postfix dir
		$postfixDirectory = $this->generatePostfixDirectory($isThumb);

		// get the base path for uploaded files
		$baseDirectory = $this->board->getBoardUploadedFilesDirectory();

		// get the base path for uploaded files
		$uploadDirectory = $baseDirectory . $postfixDirectory;

		// return upload dir
		return $uploadDirectory;
	}

	private function generatePostfixDirectory(bool $isThumb = false): string {
		// relative image directory
		$imageDirectory = $this->board->getConfigValue('IMG_DIR');

		// relative thumbnail directory
		$thumbnailDirectory = $this->board->getConfigValue('THUMB_DIR');

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

	private function generateFullPath(string $uploadDirectory, bool $isThumb = false): string {
		// file stored name
		$storedFileName = $this->fileEntry->getStoredFileName();

		// file extension
		$fileExtension = $this->fileEntry->getFileExt();

		// use a thumbnail filename + extension for a thumbnail
		if($isThumb) {
			// thumbnail file name with the s appended
			$pathFileName = $storedFileName . 's';

			// thumbnail extension from config 
			$pathExtension = $this->board->getConfigValue('THUMB_SETTING.Format', 'jpg');

		} 
		// get the regular filename + extension
		else {
			// file name
			$pathFileName = $storedFileName;

			// extension
			$pathExtension = $fileExtension;
		}

		// build the full path
		$filePath = $uploadDirectory . $pathFileName . '.' . $pathExtension;

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