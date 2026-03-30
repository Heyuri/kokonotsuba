<?php

namespace Kokonotsuba\post;

use ArrayAccess;
use JsonSerializable;

class Post implements ArrayAccess, JsonSerializable {
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
		'attachment_is_animated', 'attachment_is_deleted', 'attachment_timestamp_added',
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

	// ArrayAccess (backward compatibility)
	public function offsetExists(mixed $offset): bool {
		return match($offset) {
			'attachments' => true,
			'deleted_attachments' => true,
			'staff_notes' => true,
			'votes' => $this->votes !== null,
			default => array_key_exists($offset, $this->data),
		};
	}

	public function offsetGet(mixed $offset): mixed {
		return match($offset) {
			'attachments' => $this->attachments,
			'deleted_attachments' => $this->deletedAttachments,
			'staff_notes' => $this->staffNotes,
			'votes' => $this->votes,
			default => $this->data[$offset] ?? null,
		};
	}

	public function offsetSet(mixed $offset, mixed $value): void {
		match($offset) {
			'attachments' => $this->attachments = $value,
			'deleted_attachments' => $this->deletedAttachments = $value,
			'staff_notes' => $this->staffNotes = $value,
			'votes' => $this->votes = $value,
			default => $this->data[$offset] = $value,
		};
	}

	public function offsetUnset(mixed $offset): void {
		match($offset) {
			'attachments' => $this->attachments = [],
			'deleted_attachments' => $this->deletedAttachments = [],
			'staff_notes' => $this->staffNotes = [],
			'votes' => $this->votes = null,
			default => (function() use ($offset) { unset($this->data[$offset]); })(),
		};
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
