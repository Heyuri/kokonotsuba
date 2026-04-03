<?php

namespace Kokonotsuba\module_classes\listeners;

use Kokonotsuba\post\Post;

trait OpeningPostListenerTrait {
	protected function listenOpeningPost(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('OpeningPost',
			function(array &$templateValues, Post &$post, array &$threadPosts) use ($methodName) {
				$this->$methodName($templateValues, $post, $threadPosts);
			},
			$priority
		);
	}
}
