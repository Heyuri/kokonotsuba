<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait PostCommentListenerTrait {
	protected function listenPostComment(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostComment',
			function(string &$postComment, ?Post &$post = null, bool $isThreadView = false) use ($methodName) {
				$this->$methodName($postComment, $post, $isThreadView);
			},
			$priority
		);
	}
}
