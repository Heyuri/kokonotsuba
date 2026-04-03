<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait ThreadPostListenerTrait {
	protected function listenThreadPost(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadPost',
			function(array &$arrLabels, Post $opPost, array $threadPosts, bool $isStatic) use ($methodName) {
				$this->$methodName($arrLabels, $opPost, $threadPosts, $isStatic);
			},
			$priority
		);
	}
}
