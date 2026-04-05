<?php

namespace Kokonotsuba\thread;

use JsonSerializable;

class Thread implements JsonSerializable {
	// Core identity
	public $thread_uid = '';
	public $post_op_post_uid = 0;
	public $post_op_number = 0;
	public $boardUID = 0;

	// Timestamps
	public $last_bump_time = '';
	public $last_reply_time = '';
	public $thread_created_time = '';

	// Counts
	public $number_of_posts = 0;

	// Deletion state
	public $thread_deleted = false;
	public $thread_attachment_deleted = false;
	public $by_proxy = false;

	// Sticky
	public $is_sticky = false;

	// Theme data
	public $background_hex_color = null;
	public $reply_background_hex_color = null;
	public $text_hex_color = null;
	public $background_image_url = null;
	public $raw_styling = null;
	public $audio = null;

	// Theme metadata (from JOINed theme table)
	public $theme_date_added = null;
	public $theme_added_by = null;

	public function __construct(array $data = []) {
		if (!empty($data)) {
			foreach ($data as $key => $value) {
				if (property_exists($this, $key)) {
					$this->$key = $value;
				}
			}
		}
	}

	// Core identity
	public function getUid(): string { return (string)$this->thread_uid; }
	public function getOpPostUid(): int { return (int)$this->post_op_post_uid; }
	public function getOpNumber(): int { return (int)$this->post_op_number; }
	public function getBoardUID(): int { return (int)$this->boardUID; }

	// Timestamps
	public function getLastBumpTime(): string { return (string)$this->last_bump_time; }
	public function getLastReplyTime(): string { return (string)$this->last_reply_time; }
	public function getCreatedTime(): string { return (string)$this->thread_created_time; }

	// Counts
	public function getPostCount(): int { return (int)$this->number_of_posts; }

	// Deletion state
	public function isThreadDeleted(): bool { return (bool)$this->thread_deleted; }
	public function isAttachmentDeleted(): bool { return (bool)$this->thread_attachment_deleted; }
	public function isByProxy(): bool { return (bool)$this->by_proxy; }
	public function isHardDeleted(): bool { return $this->isThreadDeleted() && !$this->isAttachmentDeleted(); }

	// Sticky
	public function isSticky(): bool { return (bool)$this->is_sticky; }

	// Theme data
	public function getBackgroundColor(): ?string { return $this->background_hex_color; }
	public function getReplyBackgroundColor(): ?string { return $this->reply_background_hex_color; }
	public function getTextColor(): ?string { return $this->text_hex_color; }
	public function getBackgroundImageUrl(): ?string { return $this->background_image_url; }
	public function getRawStyling(): ?string { return $this->raw_styling; }
	public function getAudio(): ?string { return $this->audio; }

	// Theme metadata (from JOINed theme table)
	public function getThemeDateAdded(): ?string { return $this->theme_date_added; }
	public function getThemeAddedBy(): ?string { return $this->theme_added_by; }

	public function toArray(): array {
		return get_object_vars($this);
	}

	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
