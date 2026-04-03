<?php

namespace Kokonotsuba\module_classes\listeners;

trait ViewedThreadListenerTrait {
	protected function listenViewedThread(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ViewedThread',
			function(array &$templateValues, array &$threadData) use ($methodName) {
				$this->$methodName($templateValues, $threadData);
			},
			$priority
		);
	}
}
