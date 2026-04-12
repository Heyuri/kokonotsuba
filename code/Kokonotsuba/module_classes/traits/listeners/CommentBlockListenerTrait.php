<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait CommentBlockListenerTrait {
	protected function listenCommentBlock(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('CommentBlock',
			function(string &$commentBlock) use ($methodName) {
				$this->$methodName($commentBlock);
			},
			$priority
		);
	}
}
