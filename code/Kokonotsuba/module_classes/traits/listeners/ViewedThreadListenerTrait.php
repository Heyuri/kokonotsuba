<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use Kokonotsuba\thread\ThreadData;

trait ViewedThreadListenerTrait {
	protected function listenViewedThread(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ViewedThread',
			function(array &$templateValues, ThreadData &$threadData) use ($methodName) {
				$this->$methodName($templateValues, $threadData);
			},
			$priority
		);
	}
}
