<?php

namespace Kokonotsuba\module_classes\traits\listeners;

trait IncludeHtmlTrait {
	protected function registerHeaderHtml(string $html, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('ModuleHeader',
			function(string &$moduleHeader) use ($html) {
				$moduleHeader .= $html;
			},
			$priority
		);
	}
}
