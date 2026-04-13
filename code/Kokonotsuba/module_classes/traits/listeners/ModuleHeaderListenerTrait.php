<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait ModuleHeaderListenerTrait {
	protected function listenModuleHeader(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ModuleHeader',
			function(string &$moduleHeader) use ($methodName) {
				$this->$methodName($moduleHeader);
			},
			$priority
		);
	}
}
