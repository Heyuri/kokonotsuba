<?php

namespace Kokonotsuba\module_classes\listeners;

use Kokonotsuba\thread\Thread;

trait ThreadCssClassListenerTrait {
	protected function listenThreadCssClass(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadCssClass',
			function(string &$threadCssClasses, Thread &$thread) use ($methodName) {
				$this->$methodName($threadCssClasses, $thread);
			},
			$priority
		);
	}
}
