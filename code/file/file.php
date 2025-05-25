<?php

// encapsulation of a file from a request, needed for inserting posts
class file {
	private readonly string $extention;	// file extension
	private readonly int $timeInMillisecond; // unix time in miliseconds
	private readonly string $temporaryFileName;	// tmp file name/path
	private readonly string $fileName;	// file name
	private readonly int $fileSize;	// file size
	private int $imgW;		// width of the image
	private int $imgH;		// height of the image
	private readonly string $md5chksum;		// md5 hash of the image
	private readonly string $mimeType; // mime type of the file
	private readonly int $fileStatus; // upfile status
	
	public function __construct(string $extention = '',
	 int $timeInMillisecond = 0,
	 string $temporaryFileName = '',
	 string $fileName = '',
	 int $fileSize = 0,
	 int $imgW = 0,
	 int $imgH = 0,
	 string $md5chksum = '',
	 string $mimeType = '',
	 int $fileStatus = 0) {
		$this->extention = $extention;
		$this->timeInMillisecond = $timeInMillisecond;
		$this->temporaryFileName = $temporaryFileName;
		$this->fileName = $fileName;
        $this->fileSize = $fileSize;
		$this->imgW = $imgW;
		$this->imgH = $imgH;
		$this->md5chksum = $md5chksum;
		$this->mimeType = $mimeType;
		$this->fileStatus = $fileStatus;
	}

	public function getExtention(): string {
		$extention = $this->extention ? '.' . $this->extention : '';
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
}
