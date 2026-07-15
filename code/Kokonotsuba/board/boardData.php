<?php

namespace Kokonotsuba\board;

class boardData {
	public int $board_uid;
	public ?string $board_identifier;
	public ?string $board_title;
	public ?string $board_sub_title;
	public ?string $storage_directory_name;
	public ?string $date_added;
	public ?string $board_file_url;
	public ?string $subdomain = '';
	public bool $listed = false;

	// Getters
	public function getBoardUID(): int {
		return intval($this->board_uid);
	}

	public function getBoardTitle(): string {
		return $this->board_title ?? '';
	}

	public function getBoardSubTitle(): string {
		return $this->board_sub_title ?? '';
	}

	public function getBoardStorageDirName(): string {
		return $this->storage_directory_name ?? '';
	}

	public function getDateAdded(): string {
		return $this->date_added ?? '';
	}

	public function getBoardIdentifier(): string {
		return $this->board_identifier ?? '';
	}

	/** Subdomain this board is served from ('cgi', 'dis', …), or '' for the site-wide host. */
	public function getBoardSubdomain(): string {
		return $this->subdomain ?? '';
	}

	public function getBoardListed(): bool {
		return $this->listed ?? false;
	}

}