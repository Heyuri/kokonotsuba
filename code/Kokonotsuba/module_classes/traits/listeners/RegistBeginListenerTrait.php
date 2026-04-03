<?php

namespace Kokonotsuba\module_classes\listeners;

trait RegistBeginListenerTrait {
	protected function listenRegistBegin(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('RegistBegin',
			function(array &$registInfo) use ($methodName) {
				$this->$methodName($registInfo);
			},
			$priority
		);
	}
}
