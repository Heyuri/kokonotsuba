<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait FormFuncsListenerTrait {
	protected function listenFormFuncs(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('FormFuncs',
			function(string &$formFuncsHtml) use ($methodName) {
				$this->$methodName($formFuncsHtml);
			},
			$priority
		);
	}
}
