<?php

namespace Kokonotsuba\Modules\ads;

use function Puchiko\strings\sanitizeStr;

class adEntry {
	public int $id;
	public string $slot;
	public string $type;
	public ?string $src;
	public ?string $href;
	public ?string $alt;
	public ?string $html;
	public int $enabled;
	public string $date_added;

	public function toAdminTemplateRow(): array {
		$timestamp = strtotime($this->date_added);
		$displayDate = ($timestamp !== false) ? date('Y-m-d', $timestamp) : $this->date_added;

		return [
			'{$ID}'              => (string)$this->id,
			'{$SLOT}'            => sanitizeStr($this->slot),
			'{$TYPE}'            => sanitizeStr($this->type),
			'{$TYPE_IMAGE_SEL}'  => $this->type === 'image'  ? 'selected' : '',
			'{$TYPE_SCRIPT_SEL}' => $this->type === 'script' ? 'selected' : '',
			'{$SRC}'             => sanitizeStr((string)($this->src  ?? '')),
			'{$HREF}'            => sanitizeStr((string)($this->href ?? '')),
			'{$ALT}'             => sanitizeStr((string)($this->alt  ?? '')),
			'{$HTML}'            => sanitizeStr((string)($this->html ?? '')),
			'{$ENABLED}'         => $this->enabled ? '1' : '',
			'{$DATE}'            => sanitizeStr($displayDate),
		];
	}
}
