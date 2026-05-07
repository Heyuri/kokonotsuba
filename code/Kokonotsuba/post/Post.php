<?php

namespace Kokonotsuba\post;

use JsonSerializable;

class Post implements JsonSerializable {
	private array $data;
	private array $attachments = [];
	private array $deletedAttachments = [];
	private array $staffNotes = [];
	private ?array $votes = null;

	private const EXCLUDED_COLUMNS = [
		'attachment_id', 'attachment_file_name', 'attachment_stored_filename',
		'attachment_file_ext', 'attachment_file_md5', 'attachment_file_size',
		'attachment_file_width', 'attachment_file_height', 'attachment_thumb_width',
		'attachment_thumb_height', 'attachment_mime_type', 'attachment_is_hidden',
			'attachment_is_animated', 'attachment_is_spoilered', 'attachment_is_deleted', 'attachment_timestamp_added',
		'votes_total_count', 'votes_yeah_count', 'votes_nope_count',
		'note_id', 'note_submitted', 'note_added_by', 'note_text', 'note_added_by_username',
	];

	public function __construct(array $data = []) {
		foreach (self::EXCLUDED_COLUMNS as $col) {
			unset($data[$col]);
		}
		$this->data = $data;
	}

	// Core identity
	public function getUid(): int { return (int)($this->data['post_uid'] ?? 0); }
	public function getNumber(): int { return (int)($this->data['no'] ?? 0); }
	public function getBoardUID(): int { return (int)($this->data['boardUID'] ?? 0); }
	public function getThreadUid(): string { return (string)($this->data['thread_uid'] ?? ''); }
	public function isOp(): bool { return (bool)($this->data['is_op'] ?? false); }
	public function getPostPosition(): int { return (int)($this->data['post_position'] ?? 0); }

	// Content
	public function getName(): string { return (string)($this->data['name'] ?? ''); }
	public function getTripcode(): string { return (string)($this->data['tripcode'] ?? ''); }
	public function getSecureTripcode(): string { return (string)($this->data['secure_tripcode'] ?? ''); }
	public function getCapcode(): string { return (string)($this->data['capcode'] ?? ''); }
	public function getEmail(): string { return (string)($this->data['email'] ?? ''); }
	public function getSubject(): string { return (string)($this->data['sub'] ?? ''); }
	public function getComment(): string { return (string)($this->data['com'] ?? ''); }
	public function getCategory(): string { return (string)($this->data['category'] ?? ''); }
	public function getTag(): string { return (string)($this->data['tag'] ?? ''); }
	public function getIp(): string { return (string)($this->data['host'] ?? ''); }
	public function getTimestamp(): string { return (string)($this->data['now'] ?? ''); }
	public function getRoot(): string { return (string)($this->data['root'] ?? ''); }
	public function getPassword(): string { return (string)($this->data['pwd'] ?? ''); }
	public function getPosterHash(): string { return (string)($this->data['poster_hash'] ?? ''); }

	// Status/Flags
	public function getStatus(): string { return (string)($this->data['status'] ?? ''); }
	public function getFlags(): FlagHelper { return new FlagHelper($this->getStatus()); }

	// Deletion state
	public function getOpenFlag(): int { return (int)($this->data['open_flag'] ?? 0); }
	public function isFileOnlyDeleted(): bool { return (bool)($this->data['file_only_deleted'] ?? false); }
	public function isByProxy(): bool { return (bool)($this->data['by_proxy'] ?? false); }
	public function isDeleted(): bool { return $this->getOpenFlag() === 1 && !$this->isFileOnlyDeleted(); }

	// Thread context
	public function getOpNumber(): int { return (int)($this->data['post_op_number'] ?? 0); }

	// Nested data
	public function getAttachments(): array { return $this->attachments; }
	public function getDeletedAttachments(): array { return $this->deletedAttachments; }
	public function getStaffNotes(): array { return $this->staffNotes; }
	public function getVotes(): ?array { return $this->votes; }
	public function hasAttachments(): bool { return !empty($this->attachments); }
	public function getAttachmentById(int $id): ?array { return $this->attachments[$id] ?? null; }
	public function getFirstAttachment(): ?array {
		$key = array_key_first($this->attachments);
		return $key !== null ? $this->attachments[$key] : null;
	}

	// Mutators for hydration
	public function addAttachment(int $id, array $attachment): void {
		$this->attachments[$id] = $attachment;
	}

	public function addDeletedAttachment(int $fileId, array $meta): void {
		$this->deletedAttachments[$fileId] = $meta;
	}

	public function addStaffNote(int $noteId, array $note): void {
		if (!isset($this->staffNotes[$noteId])) {
			$this->staffNotes[$noteId] = $note;
		}
	}

	public function setVotes(array $votes): void { $this->votes = $votes; }
	public function setAttachments(array $attachments): void { $this->attachments = $attachments; }
	public function setDeletedAttachments(array $deleted): void { $this->deletedAttachments = $deleted; }

	public function setComment(string $comment): void {
		$this->data['com'] = $comment;
	}

	/** Protected accessor for subclasses to read from the private data array. */
	protected function get(string $key, mixed $default = null): mixed {
		return $this->data[$key] ?? $default;
	}

	// Dynamic data field access (for JOINed columns like deleted_post_id)
	public function getDeletedPostId(): ?int {
		$val = $this->data['deleted_post_id'] ?? null;
		return $val !== null ? (int)$val : null;
	}

	public function toArray(): array {
		$arr = $this->data;
		$arr['attachments'] = $this->attachments;
		$arr['deleted_attachments'] = $this->deletedAttachments;
		$arr['staff_notes'] = $this->staffNotes;
		if ($this->votes !== null) {
			$arr['votes'] = $this->votes;
		}
		return $arr;
	}

	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
