<?php
/*
* Quote link object for Kokonotsuba!
* Encapsulates a row from the quote link table
*/

class quoteLink {
	public readonly int $quotelink_id;
	public readonly int $board_uid;
	public readonly int $host_post_uid;
	public readonly int $target_post_uid;

	// Getters
	public function getQuoteLinkId(): int {
		return $this->quotelink_id;
	}

	public function getBoardUid(): int {
		return $this->board_uid;
	}

	public function getHostPostUid(): int {
		return $this->host_post_uid;
	}

	public function getTargetPostUid(): int {
		return $this->target_post_uid;
	}
}