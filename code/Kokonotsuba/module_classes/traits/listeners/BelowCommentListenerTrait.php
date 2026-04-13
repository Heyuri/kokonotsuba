<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait BelowCommentListenerTrait {
	protected function listenBelowComment(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('BelowComment',
			function(string &$belowComment, Post &$post, array &$threadPosts, bool &$adminMode) use ($methodName) {
				$this->$methodName($belowComment, $post, $threadPosts, $adminMode);
			},
			$priority
		);
	}
}
