<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\post\Post;

trait PostCssClassListenerTrait {
	protected function listenPostCssClass(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PostCssClass',
			function(string &$postCssClasses, Post &$post) use ($methodName) {
				$this->$methodName($postCssClasses, $post);
			},
			$priority
		);
	}
}
