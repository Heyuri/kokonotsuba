<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait ThreadSeparateListenerTrait {
	protected function listenThreadSeparate(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ThreadSeparate',
			function(string &$html, int $threadIterator) use ($methodName) {
				$this->$methodName($html, $threadIterator);
			},
			$priority
		);
	}
}
