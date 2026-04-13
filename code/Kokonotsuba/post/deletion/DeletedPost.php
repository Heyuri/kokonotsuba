<?php

namespace Kokonotsuba\post\deletion;

use Kokonotsuba\post\Post;

class DeletedPost extends Post {
	// Deletion record identity
	public function getDeletedPostId(): int { return (int)$this->get('deleted_post_id', 0); }

	// Deletion metadata
	public function getDeletedAt(): ?string { return $this->get('deleted_at'); }
	public function getDeletedBy(): ?int {
		$val = $this->get('deleted_by');
		return $val !== null ? (int)$val : null;
	}
	public function getDeletedByUsername(): ?string { return $this->get('deleted_by_username'); }

	// Restoration metadata
	public function getRestoredAt(): ?string { return $this->get('restored_at'); }
	public function getRestoredBy(): ?int {
		$val = $this->get('restored_by');
		return $val !== null ? (int)$val : null;
	}
	public function getRestoredByUsername(): ?string { return $this->get('restored_by_username'); }

	// File-only deletion
	public function getFileId(): ?int {
		$val = $this->get('file_id');
		return $val !== null ? (int)$val : null;
	}
	public function getFileOnlyDeleted(): bool { return (bool)$this->get('file_only_deleted', false); }
}
