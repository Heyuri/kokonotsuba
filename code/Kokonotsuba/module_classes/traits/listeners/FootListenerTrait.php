<?php

namespace Kokonotsuba\module_classes\listeners;

trait FootListenerTrait {
	protected function listenFoot(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('Foot',
			function(string &$footer) use ($methodName) {
				$this->$methodName($footer);
			},
			$priority
		);
	}
}
