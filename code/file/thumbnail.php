<?php

class thumbnail {
	private readonly int $thumbnailWidth;
	private readonly int $thumbnailHeight;

	public function __construct(int $thumbnailWidth = 0, int $thumbnailHeight = 0) {
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
