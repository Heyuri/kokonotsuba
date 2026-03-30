<?php

namespace Kokonotsuba\thread;

use ArrayAccess;
use JsonSerializable;

class Thread implements ArrayAccess, JsonSerializable {
	private array $data;

	public function __construct(array $data = []) {
		$this->data = $data;
	}

	// Core identity
	public function getUid(): string { return (string)($this->data['thread_uid'] ?? ''); }
	public function getOpPostUid(): int { return (int)($this->data['post_op_post_uid'] ?? 0); }
	public function getOpNumber(): int { return (int)($this->data['post_op_number'] ?? 0); }
	public function getBoardUID(): int { return (int)($this->data['boardUID'] ?? 0); }

	// Timestamps
	public function getLastBumpTime(): string { return (string)($this->data['last_bump_time'] ?? ''); }
	public function getLastReplyTime(): string { return (string)($this->data['last_reply_time'] ?? ''); }
	public function getCreatedTime(): string { return (string)($this->data['thread_created_time'] ?? ''); }

	// Counts
	public function getPostCount(): int { return (int)($this->data['number_of_posts'] ?? 0); }

	// Deletion state
	public function isThreadDeleted(): bool { return (bool)($this->data['thread_deleted'] ?? false); }
	public function isAttachmentDeleted(): bool { return (bool)($this->data['thread_attachment_deleted'] ?? false); }
	public function isByProxy(): bool { return (bool)($this->data['by_proxy'] ?? false); }
	public function isHardDeleted(): bool { return $this->isThreadDeleted() && !$this->isAttachmentDeleted(); }

	// Sticky (thread-level column)
	public function isSticky(): bool { return (bool)($this->data['is_sticky'] ?? false); }

	// Theme data
	public function getBackgroundColor(): ?string { return $this->data['background_hex_color'] ?? null; }
	public function getReplyBackgroundColor(): ?string { return $this->data['reply_background_hex_color'] ?? null; }
	public function getTextColor(): ?string { return $this->data['text_hex_color'] ?? null; }
	public function getBackgroundImageUrl(): ?string { return $this->data['background_image_url'] ?? null; }
	public function getRawStyling(): ?string { return $this->data['raw_styling'] ?? null; }
	public function getAudio(): ?string { return $this->data['audio'] ?? null; }

	// ArrayAccess (backward compatibility)
	public function offsetExists(mixed $offset): bool {
		return array_key_exists($offset, $this->data);
	}

	public function offsetGet(mixed $offset): mixed {
		return $this->data[$offset] ?? null;
	}

	public function offsetSet(mixed $offset, mixed $value): void {
		$this->data[$offset] = $value;
	}

	public function offsetUnset(mixed $offset): void {
		unset($this->data[$offset]);
	}

	public function toArray(): array {
		return $this->data;
	}

	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
