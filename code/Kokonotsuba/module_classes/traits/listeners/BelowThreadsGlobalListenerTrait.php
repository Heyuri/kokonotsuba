<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait BelowThreadsGlobalListenerTrait {
	protected function listenBelowThreadsGlobal(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('BelowThreadsGlobal',
			function(string &$html) use ($methodName) {
				$this->$methodName($html);
			},
			$priority
		);
	}
}
