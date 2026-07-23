<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;
use Kokonotsuba\thread\Thread;

trait ThreadWidgetListenerTrait {
	protected function listenThreadWidget(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadWidget',
			function(array &$widgetArray, Post &$openingPost, array &$threadPosts, ?Thread $thread = null) use ($methodName) {
				$this->$methodName($widgetArray, $openingPost, $threadPosts, $thread);
			},
			$priority
		);
	}
}
