<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

/** Adds a module's own deleted posts to the "This thread has n deleted posts" notice. */
trait ThreadDeletedPostCountListenerTrait {
	protected function listenThreadDeletedPostCount(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadDeletedPostCount',
			/** @param Post[] $posts the posts drawn for the thread */
			function(int &$count, array $posts) use ($methodName) {
				$this->$methodName($count, $posts);
			},
			$priority
		);
	}
}
