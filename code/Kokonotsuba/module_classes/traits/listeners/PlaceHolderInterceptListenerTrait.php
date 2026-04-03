<?php

namespace Kokonotsuba\module_classes\listeners;

trait PlaceHolderInterceptListenerTrait {
	protected function listenPlaceHolderIntercept(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PlaceHolderIntercept',
			function(array &$placeholderArray) use ($methodName) {
				$this->$methodName($placeholderArray);
			},
			$priority
		);
	}
}
