<?php

namespace Kokonotsuba\post\deletion;

use Kokonotsuba\post\Post;

class DeletedPost extends Post {
	// Deletion record identity
	public function getDeletedPostId(): int { return (int)($this['deleted_post_id'] ?? 0); }

	// Deletion metadata
	public function getDeletedAt(): ?string { return $this['deleted_at'] ?? null; }
	public function getDeletedBy(): ?int {
		$val = $this['deleted_by'] ?? null;
		return $val !== null ? (int)$val : null;
	}
	public function getDeletedByUsername(): ?string { return $this['deleted_by_username'] ?? null; }

	// Restoration metadata
	public function getRestoredAt(): ?string { return $this['restored_at'] ?? null; }
	public function getRestoredBy(): ?int {
		$val = $this['restored_by'] ?? null;
		return $val !== null ? (int)$val : null;
	}
	public function getRestoredByUsername(): ?string { return $this['restored_by_username'] ?? null; }

	// File-only deletion
	public function getFileId(): ?int {
		$val = $this['file_id'] ?? null;
		return $val !== null ? (int)$val : null;
	}
	public function getFileOnlyDeleted(): bool { return (bool)($this['file_only_deleted'] ?? false); }
}
