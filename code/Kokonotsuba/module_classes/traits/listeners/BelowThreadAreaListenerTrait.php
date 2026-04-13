<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait BelowThreadAreaListenerTrait {
	protected function listenBelowThreadArea(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('BelowThreadArea',
			function(string &$threadRear, bool $isIndex) use ($methodName) {
				$this->$methodName($threadRear, $isIndex);
			},
			$priority
		);
	}
}
