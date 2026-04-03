<?php

namespace Kokonotsuba\module_classes\listeners;

use Kokonotsuba\post\Post;

trait PostCommentListenerTrait {
	protected function listenPostComment(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostComment',
			function(string &$postComment, Post &$post) use ($methodName) {
				$this->$methodName($postComment, $post);
			},
			$priority
		);
	}
}
