<?php

namespace Kokonotsuba\module_classes\listeners;

trait PageTopListenerTrait {
	protected function listenPageTop(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PageTop',
			function(string &$bannerHtml) use ($methodName) {
				$this->$methodName($bannerHtml);
			},
			$priority
		);
	}
}
