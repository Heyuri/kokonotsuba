<?php

namespace Kokonotsuba\module_classes\listeners;

trait AboveThreadAreaListenerTrait {
	protected function listenAboveThreadArea(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('AboveThreadArea',
			function(string &$threadFront, bool $isIndex) use ($methodName) {
				$this->$methodName($threadFront, $isIndex);
			},
			$priority
		);
	}
}
