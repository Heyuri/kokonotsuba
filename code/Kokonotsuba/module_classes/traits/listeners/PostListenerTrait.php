<?php

namespace Kokonotsuba\module_classes\listeners;

use Kokonotsuba\post\Post;
use Kokonotsuba\board\board;

trait PostListenerTrait {
	protected function listenPost(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('Post',
			function(array &$templateValues, Post &$post, array &$threadPosts, board &$board, bool &$adminMode) use ($methodName) {
				$this->$methodName($templateValues, $post, $threadPosts, $board, $adminMode);
			},
			$priority
		);
	}
}
