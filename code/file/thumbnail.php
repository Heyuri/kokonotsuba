<?php

class thumbnail {
	private int $thumbnailWidth;
	private int $thumbnailHeight;
	private string $thumbnailFileName;

	public function __construct(int $thumbnailWidth = 0, int $thumbnailHeight = 0, string $thumbnailFileName = '') {
		$this->thumbnailWidth = $thumbnailWidth;
		$this->thumbnailHeight = $thumbnailHeight;
		$this->thumbnailFileName = $thumbnailFileName;
	}

	// GET
	public function getThumbnailWidth(): int {
		return $this->thumbnailWidth ?? 0;
	}

	public function getThumbnailHeight(): int {
		return $this->thumbnailHeight ?? 0;
	}

	public function getThumbnailFileName(): string {
		return $this->thumbnailFileName ?? '';
	}

	// set
	public function setThumbnailWidth(int $thumbnailWidth): void {
		$this->thumbnailWidth = $thumbnailWidth;
	}

	public function setThumbnailHeight(int $thumbnailHeight): void {
		$this->thumbnailHeight = $thumbnailHeight;
	}

	public function setThumbnailFileName(string $thumbnailFileName): void {
		$this->thumbnailFileName = $thumbnailFileName;
	}

}
