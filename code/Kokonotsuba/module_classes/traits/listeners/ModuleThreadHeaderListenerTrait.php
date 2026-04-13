<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\thread\Thread;

trait ModuleThreadHeaderListenerTrait {
	protected function listenModuleThreadHeader(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ModuleThreadHeader',
			function(string &$threadHeader, Thread &$thread) use ($methodName) {
				$this->$methodName($threadHeader, $thread);
			},
			$priority
		);
	}
}
