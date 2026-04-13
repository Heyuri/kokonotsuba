<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait ThreadReplyListenerTrait {
	protected function listenThreadReply(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadReply',
			function(array &$templateValues, Post &$post, array &$threadPosts) use ($methodName) {
				$this->$methodName($templateValues, $post, $threadPosts);
			},
			$priority
		);
	}
}
