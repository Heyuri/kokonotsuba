<?php

// encapsulation of a file from a request, needed for inserting posts
class file {
	public function __construct(
		private string $extention = '',
	 	private int $timeInMillisecond = 0,
		private string $temporaryFileName = '',
		private string $fileName = '',
		private int $fileSize = 0,
		private int $imgW = 0,
		private int $imgH = 0,
		private string $md5chksum = '',
		private string $mimeType = '',
		private int $fileStatus = 0,
		private int $index = 0) {}

	public function getExtention(): string {
		$extention = $this->extention ?? '';
		return $extention;
	}

	public function getTimeInMilliseconds(): int {
		return $this->timeInMillisecond ?? 0;
	}

	public function getTemporaryFileName(): string {
		return $this->temporaryFileName ?? '';
	}

	public function getFileName(): string {
		return $this->fileName ?? '';
	}

	public function getFileSize(): int {
		return $this->fileSize ?? 0;
	}

	public function getImageWidth(): int {
		return $this->imgW ?? 0;
	}

	public function getImageHeight(): int {
		return $this->imgH ?? 0;
	}

	public function getMd5Chksum(): string {
		return $this->md5chksum ?? '';
	}

	public function getMimeType(): string {
		return $this->mimeType ?? '';
	}

	public function getFileStatus(): int {
		return $this->fileStatus ?? 0;
	}

	public function getIndex(): int {
		return $this->index ?? 0;
	}
}
