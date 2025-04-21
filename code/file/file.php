<?php

// encapsulation of relevant file data needed for inserting posts
class file {
	private readonly string $extention;	// file extension
	private readonly string $fileName;	// file name
	private readonly string $fileSize;	// file size
	private readonly int $imgW;		// width of the image
	private readonly int $imgH;		// height of the image
	private readonly string $md5chksum;		// md5 hash of the image
	private readonly string $dest;		// full path of the image
	private readonly string $mimeType; // mime type of the file

	public function __construct(string $extention = '', string $fileName = '', string $fileSize = '', int $imgW = 0, int $imgH = 0, string $md5chksum = '', string $dest = '', string $mimeType = '') {
		$this->extention = $extention;
		$this->fileName = $fileName;
        $this->fileSize = $fileSize;
		$this->imgW = $imgW;
		$this->imgH = $imgH;
		$this->md5chksum = $md5chksum;
		$this->dest = $dest;
		$this->mimeType = $mimeType;
	}

	public function getExtention(): string {
		return $this->extention ?? '';
	}

	public function getFileName(): string {
		return $this->fileName ?? '';
	}

	public function getFileSize(): string {
		return $this->fileSize ?? '';
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

	public function getDest(): string {
		return $this->dest ?? '';
	}

	public function getMimeType(): string {
		return $this->mimeType ?? '';
	}
}
