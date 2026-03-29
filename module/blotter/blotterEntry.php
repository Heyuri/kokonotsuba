<?php

namespace Kokonotsuba\Modules\blotter;

use function Puchiko\strings\sanitizeStr;

class blotterEntry {
	public int $id;
	public string $blotter_content;
	public ?int $added_by;
	public ?string $added_by_username;
	public string $date_added;

	private function getDisplayDate(): string {
		$timestamp = strtotime($this->date_added);

		if ($timestamp === false) {
			return $this->date_added;
		}

		return date('Y-m-d', $timestamp);
	}

	public function toAdminTemplateRow(): array {
		return [
			'{$DATE}' => sanitizeStr($this->getDisplayDate()),
			'{$ADDED_BY}' => sanitizeStr($this->added_by_username ?? ''),
			'{$COMMENT}' => sanitizeStr($this->blotter_content),
			'{$UID}' => sanitizeStr((string) $this->id),
		];
	}

	public function toPublicTemplateRow(): array {
		return [
			'{$DATE}' => sanitizeStr($this->getDisplayDate()),
			'{$COMMENT}' => sanitizeStr($this->blotter_content, true, true, true),
		];
	}
}