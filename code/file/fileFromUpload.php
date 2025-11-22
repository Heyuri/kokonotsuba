<?php
/*
*
*/

class fileFromUpload {
	private file $file;

	public function __construct(file $file) {
		$this->file = $file;
	}

	// in case you want to delete the temp file, however PHP will handle that itself
	public function delete(): void {
		unlink($this->file->getTemporaryFileName());
	}

	// get the file
	public function getFile(): file {
		return $this->file;
	}

	// Upload image
	public function saveFile(string $directory): void {
		$timestamp	= $this->file->getTimeInMilliseconds();
		$extension	= $this->file->getExtention();
		$tmpPath	= $this->file->getTemporaryFileName();
		$fileName	= $timestamp . '.' . $extension;
		$destPath	= rtrim($directory, '/') . '/' . $fileName;

		// Validate existence
		if(!is_dir($directory)) {
			throw new RuntimeException("Upload directory is not a directory or does not exist: $directory");
		}

		// Validate writable
		if(!is_writable($directory)) {
			throw new RuntimeException("Upload directory is not writable");
		}

		// Save to destination
		if(!move_uploaded_file($tmpPath, $destPath)) {
			throw new RuntimeException("Failed to move uploaded file to $destPath");
		}
	}

}