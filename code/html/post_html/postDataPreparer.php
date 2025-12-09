<?php

class postDataPreparer {
    public function __construct(
        private board $board,
    ) {}

	 /**
	 * Sanitize and format post data for rendering using associative arrays.
	 */
	public function preparePostData(array $post): array {
		// Mailto formatting
		if ($this->board->getConfigValue('CLEAR_SAGE')) {
			$email = preg_replace('/^sage( *)/i', '', $post['email']);
		}
		if ($this->board->getConfigValue('ALLOW_NONAME') == 2 && $email) {
			$now = "<a href=\"mailto:{$post['email']}\">{$post['now']}</a>";
		}

		// return post
		return $post;
	}

}