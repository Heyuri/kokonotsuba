<?php

// encapsulation of relevant file data needed for inserting posts
class file {
	private readonly string $extention;	// file extension
	private readonly string $fileName;	// file name
	private readonly string $fileSize;	// file size
	private readonly int $imgW;		// width of the image
	private readonly int $imgH;		// height of the image

	public function __construct(string $extention = '', string $fileName = '', string $fileSize = '', int $imgW = 0, int $imgH = 0) {
		$this->extention = $extention;
		$this->fileName = $fileName;
        $this->fileSize = $fileSize;
		$this->imgW = $imgW;
		$this->imgH = $imgH;
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
}
