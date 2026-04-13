<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait CommentExtrasListenerTrait {
	protected function listenCommentExtras(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('CommentExtras',
			function(string &$commentExtras) use ($methodName) {
				$this->$methodName($commentExtras);
			},
			$priority
		);
	}
}
