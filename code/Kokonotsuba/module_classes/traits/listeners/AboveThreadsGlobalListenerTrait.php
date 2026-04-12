<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait AboveThreadsGlobalListenerTrait {
	protected function listenAboveThreadsGlobal(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('AboveThreadsGlobal',
			function(string &$html) use ($methodName) {
				$this->$methodName($html);
			},
			$priority
		);
	}
}
