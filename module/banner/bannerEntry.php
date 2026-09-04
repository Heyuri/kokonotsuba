<?php

namespace Kokonotsuba\Modules\banner;

use Kokonotsuba\post\helper\postDateFormatter;

use function Puchiko\strings\sanitizeStr;

class bannerEntry {
	public int $id;
	public ?string $link;
	public string $banner_file_name;
	public ?string $ip_address;
	/** Rows written before presets existed are all banner ads, which is the column default. */
	public string $preset = 'ad';
	public int $is_active;
	public int $is_approved;
	public string $date_submitted;

	private function getDisplayDate(postDateFormatter $formatter): string {
		return $formatter->formatFromDateString($this->date_submitted);
	}

	private function commonRow(string $serveImageUrl, bannerPreset $preset, postDateFormatter $formatter): array {
		return [
			'{$DATE}' => $this->getDisplayDate($formatter),
			'{$LINK}' => $this->link ? sanitizeStr($this->link) : '#',
			'{$IMAGE_URL}' => sanitizeStr($serveImageUrl . '&file=' . urlencode($this->banner_file_name)),
			'{$PRESET}' => sanitizeStr($this->preset),
			'{$PRESET_LABEL}' => sanitizeStr($preset->label()),
			'{$USES_LINK}' => $preset->usesLink ? '1' : '',
			'{$BANNER_WIDTH}' => (string) $preset->width,
			'{$BANNER_HEIGHT}' => (string) $preset->height,
		];
	}

	public function toPublicTemplateRow(string $serveImageUrl, bannerPreset $preset, postDateFormatter $formatter): array {
		return $this->commonRow($serveImageUrl, $preset, $formatter);
	}

	public function toAdminTemplateRow(string $serveImageUrl, bannerPreset $preset, postDateFormatter $formatter): array {
		return $this->commonRow($serveImageUrl, $preset, $formatter) + [
			'{$ID}' => (string) $this->id,
			'{$FILE_NAME}' => sanitizeStr($this->banner_file_name),
			'{$IS_ACTIVE}' => $this->is_active ? 'Yes' : 'No',
			'{$IS_APPROVED}' => $this->is_approved ? 'Yes' : 'No',
			'{$APPROVED_CLASS}' => $this->is_approved ? 'approved' : 'unapproved',
		];
	}
}
