<?php

class thumbnail {
	private readonly int $thumbnailWidth;
	private readonly int $thumbnailHeight;

	public function __construct(int $thumbnailWidth, int $thumbnailHeight) {
		$this->thumbnailWidth = $thumbnailWidth;
		$this->thumbnailHeight = $thumbnailHeight;
	}

	public function getThumbnailWidth(): int {
		return $this->thumbnailWidth ?? 0;
	}

	public function getThumbnailHeight(): int {
		return $this->thumbnailHeight ?? 0;
	}
}
