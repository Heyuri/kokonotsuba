<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait ThreadWidgetListenerTrait {
	protected function listenThreadWidget(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadWidget',
			function(array &$widgetArray, Post &$openingPost, array &$threadPosts) use ($methodName) {
				$this->$methodName($widgetArray, $openingPost, $threadPosts);
			},
			$priority
		);
	}
}
