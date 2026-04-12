<?php

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\post\helper\postDateFormatter;
use function Puchiko\strings\sanitizeStr;

class fullBannerEntry {
	public int $id;
	public ?string $link;
	public string $banner_file_name;
	public ?string $ip_address;
	public int $is_active;
	public int $is_approved;
	public string $date_submitted;

	private function getDisplayDate(postDateFormatter $formatter): string {
		return $formatter->formatFromDateString($this->date_submitted);
	}

	public function toPublicTemplateRow(string $serveImageUrl, int $bannerWidth, int $bannerHeight, postDateFormatter $formatter): array {
		$link = $this->link ? sanitizeStr($this->link) : '#';
		$imageUrl = $serveImageUrl . '&file=' . urlencode($this->banner_file_name);

		return [
			'{$DATE}' => $this->getDisplayDate($formatter),
			'{$LINK}' => $link,
			'{$IMAGE_URL}' => sanitizeStr($imageUrl),
			'{$BANNER_WIDTH}' => (string) $bannerWidth,
			'{$BANNER_HEIGHT}' => (string) $bannerHeight,
		];
	}

	public function toAdminTemplateRow(string $serveImageUrl, int $bannerWidth, int $bannerHeight, postDateFormatter $formatter): array {
		$link = $this->link ? sanitizeStr($this->link) : '#';
		$imageUrl = $serveImageUrl . '&file=' . urlencode($this->banner_file_name);

		return [
			'{$ID}' => (string) $this->id,
			'{$DATE}' => $this->getDisplayDate($formatter),
			'{$FILE_NAME}' => sanitizeStr($this->banner_file_name),
			'{$LINK}' => $link,
			'{$IMAGE_URL}' => sanitizeStr($imageUrl),
			'{$BANNER_WIDTH}' => (string) $bannerWidth,
			'{$BANNER_HEIGHT}' => (string) $bannerHeight,
			'{$IS_ACTIVE}' => $this->is_active ? 'Yes' : 'No',
			'{$IS_APPROVED}' => $this->is_approved ? 'Yes' : 'No',
			'{$APPROVED_CLASS}' => $this->is_approved ? 'approved' : 'unapproved',
		];
	}
}
