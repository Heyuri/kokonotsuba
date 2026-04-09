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

	protected function addFormFuncLink(string $url, string $text, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('FormFuncs',
			function(string &$formFuncsHtml) use ($url, $text) {
				$formFuncsHtml .= ' | <a class="postformOption" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($text) . '</a>';
			},
			$priority
		);
	}
}
