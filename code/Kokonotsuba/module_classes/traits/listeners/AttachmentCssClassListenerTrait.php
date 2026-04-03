<?php

namespace Kokonotsuba\module_classes\listeners;

use Kokonotsuba\post\Post;

trait AttachmentCssClassListenerTrait {
	protected function listenAttachmentCssClass(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('AttachmentCssClass',
			function(string &$attachmentCssClasses, Post &$post, bool &$adminMode) use ($methodName) {
				$this->$methodName($attachmentCssClasses, $post, $adminMode);
			},
			$priority
		);
	}
}
