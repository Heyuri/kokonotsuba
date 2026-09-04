<?php

namespace Kokonotsuba\Modules\banner;

use function Kokonotsuba\libraries\_T;

/** One kind of banner: what an upload has to look like and whether readers may send one. */
final class bannerPreset {
	public function __construct(
		public readonly string $key,
		public readonly string $labelKey,
		public readonly int $width,
		public readonly int $height,
		public readonly int $maxFileSize,
		public readonly bool $allowSubmissions,
		public readonly int $submissionCooldown,
		public readonly bool $usesLink,
	) {}

	public function label(): string {
		return _T($this->labelKey);
	}

	/** Bullet list of what an upload must satisfy, for the submit form. */
	public function requirements(): array {
		return [
			_T('banner_req_dimensions', $this->width, $this->height),
			_T('banner_req_filetypes'),
			_T('banner_req_filesize', round($this->maxFileSize / 1024)),
		];
	}
}
