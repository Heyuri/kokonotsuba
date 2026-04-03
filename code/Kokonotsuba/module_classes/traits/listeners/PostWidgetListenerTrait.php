<?php

namespace Kokonotsuba\module_classes\listeners;

use Kokonotsuba\post\Post;

trait PostWidgetListenerTrait {
	protected function listenPostWidget(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostWidget',
			function(array &$widgetArray, Post &$post) use ($methodName) {
				$this->$methodName($widgetArray, $post);
			},
			$priority
		);
	}
}
