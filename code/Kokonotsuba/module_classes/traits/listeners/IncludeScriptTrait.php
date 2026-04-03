<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait IncludeScriptTrait {
	protected function registerScript(string $jsFileName, bool $defer = true, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ModuleHeader',
			function(string &$moduleHeader) use ($jsFileName, $defer) {
				$this->includeScript($jsFileName, $moduleHeader, $defer);
			},
			$priority
		);
	}
}
