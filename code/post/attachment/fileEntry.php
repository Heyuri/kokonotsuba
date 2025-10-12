<?php

class fileEntry {
	public int $id;
	public int $post_uid;
	public int $boardUID;
	public string $file_name;
	public string $stored_filename;
	public string $file_ext;
	public string $file_md5;
	public ?int $file_width;
	public ?int $file_height;
	public ?int $thumb_file_width;
	public ?int $thumb_file_height;
	public string|int $file_size;
	public string $mime_type;
	public int $is_hidden;

	public function __construct() {

	}

	public function hydrateFileEntry(int $id,
		int $post_uid,
		int $boardUID,
		string $file_name,
		string $stored_filename,
		string $file_ext,
		string $file_md5,
		?int $file_width,
		?int $file_height,
		?int $thumb_file_width,
		?int $thumb_file_height,
		string|int $file_size,
		string $mime_type,
		int $is_hidden
	): void {
		$this->id = $id;
		$this->post_uid = $post_uid;
		$this->boardUID = $boardUID;
		$this->file_name = $file_name;
		$this->stored_filename = $stored_filename;
		$this->file_ext = $file_ext;
		$this->file_md5 = $file_md5;
		$this->file_width = $file_width;
		$this->file_height = $file_height;
		$this->thumb_file_width = $thumb_file_width;
		$this->thumb_file_height = $thumb_file_height;
		$this->file_size = $file_size;
		$this->mime_type = $mime_type;
		$this->is_hidden = $is_hidden;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getPostUid(): int {
		return $this->post_uid;
	}

	public function getBoardUid(): int {
		return $this->boardUID;
	}

	public function getFileName(): string {
		return $this->file_name;
	}

	public function getStoredFilename(): string {
		return $this->stored_filename;
	}

	public function getFileExt(): string {
		return $this->file_ext;
	}

	public function getFileMd5(): string {
		return $this->file_md5;
	}

	public function getFileWidth(): int {
		return $this->file_width;
	}

	public function getFileHeight(): ?int {
		return $this->file_height;
	}

	public function getFileSize(): ?int {
		return $this->file_size;
	}

	public function getMimeType(): string {
		return $this->mime_type;
	}

	public function isHidden(): bool {
		return (bool)$this->is_hidden;
	}

}
