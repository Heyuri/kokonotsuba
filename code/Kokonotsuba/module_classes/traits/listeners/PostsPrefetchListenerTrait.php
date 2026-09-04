<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * PostsPrefetch fires once per page render with every post about to be drawn, before any
 * per-post hook. A module that looks something up per post can load it for the whole page here
 * instead of one query per post.
 */
trait PostsPrefetchListenerTrait {
	/** @param string $methodName Method taking (Post[] $posts). */
	protected function listenPostsPrefetch(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostsPrefetch',
			function(array $posts) use ($methodName) {
				$this->$methodName($posts);
			},
			$priority
		);
	}
}
