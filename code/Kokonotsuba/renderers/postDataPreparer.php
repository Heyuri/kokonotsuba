<?php

namespace Kokonotsuba\renderers;

use Kokonotsuba\board\board;
use Kokonotsuba\post\Post;

class postDataPreparer {
    public function __construct(
        private board $board,
    ) {}

	 /**
	 * Sanitize and format post data for rendering using associative arrays.
	 */
	public function preparePostData(Post $post): Post {
		// Mailto formatting
		if ($this->board->getConfigValue('CLEAR_SAGE')) {
			$email = preg_replace('/^sage( *)/i', '', $post->getEmail());
		}
		if ($this->board->getConfigValue('ALLOW_NONAME') == 2 && $email) {
			$now = "<a href=\"mailto:{$post->getEmail()}\">{$post->getTimestamp()}</a>";
		}

		// return post
		return $post;
	}

}