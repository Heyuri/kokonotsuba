<?php

/**
 * FileIO Local local storage API (Without IFS index cache)
 *
 * Use the local hard disk space as the image file storage method, and provide a set of methods for the program to manage images
 *
 * This version reverts to the behavior of the old version (5th.Release), and still uses file I/O to confirm when judging image files,
 * Avoid the problem that the image file cannot be displayed due to an error in IFS in a specific environment.
 *
 * @package PMCLibrary
 * @version $Id$
 * @date $Date$
 * @since 8th.Release
 */
class FileIOlocal extends AbstractFileIO {
	public function __construct() {
		parent::__construct();
	}

	public function init() {
		return true;
	}

	public function imageExists($imgname, $board) {
		return file_exists($this->getImagePhysicalPath($imgname, $board));
	}

	public function deleteImage($imgname, $board) {
		if (!is_array($imgname)) [$imgname = array($imgname)];

		foreach ($imgname as $i) {
			unlink($this->getImagePhysicalPath($i, $board));
		}
	}

	public function deleteImagesByBoardFiles($boardFiles) {
		if (!is_array($boardFiles)) $boardFiles = [$boardFiles];

		foreach ($boardFiles as $boardFile) {
			$fileName = $boardFile->getFilename();
			$fileUnixName = $boardFile->getUnixFileName();
			$fileBoard = $boardFile->getBoard();

			$dFile = $fileName;
			$dThumb = $this->resolveThumbName($fileUnixName, $fileBoard);
			if ($this->imageExists($dFile, $fileBoard)) $this->deleteImage($dFile, $fileBoard);
			if ($dThumb && $this->imageExists($dThumb, $fileBoard)) $this->deleteImage($dThumb, $fileBoard);

		}

	}

	private function getImagePhysicalPath($imgname, $board) {
		$config = $board->loadBoardConfig();

		$fullDirectory = '';
		$fileDir = (strpos($imgname, 's.') !== false ? $config['THUMB_DIR'] : $config['IMG_DIR']) . $imgname;

		$fullDirectory = $board->getBoardUploadedFilesDirectory();

		return $fullDirectory.$fileDir;
	}

	public function uploadImage($imgname, $imgpath, $imgsize, $board) {
		return false;
	}

	public function getImageFilesize($imgname, $board) {
		$size = filesize($this->getImagePhysicalPath($imgname, $board));
		if ($size === false) {
			$size = 0;
		}
		return $size;
	}

	public function getImageURL($imgname, $board) {
		return $this->getImageLocalURL($imgname, $board);
	}

	public function resolveThumbName($thumbPattern, $board) {
		// Load the board configuration settings
		$config = $board->loadBoardConfig();

		// Get the configured thumbnail file extension
		$thumbnailExtention = $config['THUMB_SETTING']['Format'];

		// Construct the full path to the thumbnail file
		$thumbnailBaseName = $board->getBoardUploadedFilesDirectory().$config['THUMB_DIR'] . $thumbPattern . 's.';
		
		// Return false if the thumbnail file does not exist at the constructed path
		if(file_exists($thumbnailBaseName . $thumbnailExtention)) {
			$thumbnailPath = $thumbnailBaseName . $thumbnailExtention;
		} 
		// For backwards compatability for older posts on Heyuri when it used to generate thumbnails in PNG format 
		else if(file_exists($thumbnailBaseName . 'png')) {
			$thumbnailPath = $thumbnailBaseName . 'png';
		} else {
			return false;
		}
		
		// Extract and return just the filename from the full path
		$thumbnailName = basename($thumbnailPath);

		return $thumbnailName;
	}


	
}
