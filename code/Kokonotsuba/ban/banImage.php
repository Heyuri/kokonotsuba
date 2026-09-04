<?php

namespace Kokonotsuba\ban;

/**
 * One ban image, with the size the file itself reports.
 *
 * The dimensions ride along so the <img> can carry width and height: the ban screen is a flex
 * row with the picture beside the text, and without them the text is laid out once at zero width
 * and again when the image loads. A file that cannot be measured - missing, or a format GD does
 * not read - reports 0 and simply contributes no attributes.
 */
final class banImage {
	public function __construct(
		public readonly string $url,
		public readonly int $width = 0,
		public readonly int $height = 0,
	) {}

	/** True when the file gave up a usable size. */
	public function hasDimensions(): bool {
		return $this->width > 0 && $this->height > 0;
	}

	/** `width="640" height="480"`, or '' when the file could not be measured. */
	public function dimensionAttributes(): string {
		return $this->hasDimensions()
			? 'width="' . $this->width . '" height="' . $this->height . '"'
			: '';
	}
}
