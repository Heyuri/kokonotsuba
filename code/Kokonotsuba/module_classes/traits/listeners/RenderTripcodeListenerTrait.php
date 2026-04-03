<?php

namespace Kokonotsuba\module_classes\listeners;

trait RenderTripcodeListenerTrait {
	protected function listenRenderTripcode(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('RenderTripcode',
			function(string &$nameHtml, string &$tripcode, string &$secureTripcode, string &$capcode) use ($methodName) {
				$this->$methodName($nameHtml, $tripcode, $secureTripcode, $capcode);
			},
			$priority
		);
	}
}
