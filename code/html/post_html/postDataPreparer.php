<?php

class postDataPreparer {
    public function __construct(
        private board $board,
    ) {}

	 /**
	 * Sanitize and format post data for rendering using associative arrays.
	 */
	public function preparePostData(array $post): array {
		// Basic fields
		$email = isset($post['email']) ? trim($post['email']) : '';
		$name = $post['name'] ?? '';
		$tripcode = $post['tripcode'] ?? '';
		$secure_tripcode = $post['secure_tripcode'] ?? '';
		$capcode = $post['capcode'] ?? '';
		$now = $post['now'] ?? '';
		$com = $post['com'] ?? '';
		$open_flag = $post['open_flag'] ?? 0;
		$file_only_deleted = $post['file_only_deleted'] ?? 0;
		$status = new FlagHelper($post['status']);
		
	
		// Mailto formatting
		if ($this->board->getConfigValue('CLEAR_SAGE')) {
			$email = preg_replace('/^sage( *)/i', '', $email);
		}
		if ($this->board->getConfigValue('ALLOW_NONAME') == 2 && $email) {
			$now = "<a href=\"mailto:$email\">$now</a>";
		}

		// Return everything needed
		return [
			'email' => $email,
			'name' => $name,
			'open_flag' => $open_flag,
			'file_only_deleted' => $file_only_deleted,
			'tripcode' => $tripcode,
			'secure_tripcode' => $secure_tripcode,
			'capcode' => $capcode,
			'now' => $now,
			'com' => $com,
			'no' => $post['no'],
			'is_op' => $post['is_op'],
			'post_position' => $post['post_position'],
			'sub' => $post['sub'],
			'status' => $status,
			'category' => $post['category'],
			'post_uid' => $post['post_uid'],
			'boardUID' => $post['boardUID'],
			// file
			'attachments' => $post['attachments'],
		];
	}

}