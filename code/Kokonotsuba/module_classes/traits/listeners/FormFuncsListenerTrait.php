<?php

namespace Kokonotsuba\module_classes\traits\listeners;

use function Puchiko\strings\sanitizeStr;

trait FormFuncsListenerTrait {
	protected function listenFormFuncs(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('FormFuncs',
			function(string &$formFuncsHtml) use ($methodName) {
				$this->$methodName($formFuncsHtml);
			},
			$priority
		);
	}

	protected function addFormFuncLink(string $url, string $text, bool $jsOnly = false, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('FormFuncs',
			function(string &$formFuncsHtml) use ($url, $text, $jsOnly) {
				$formFuncsHtml .= ' | <a class="postformOption' . ($jsOnly ? ' js-only' : '') . '" href="' . sanitizeStr($url) . '">' . sanitizeStr($text) . '</a>';
			},
			$priority
		);
	}
}
