<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Emit markup at the end of the body, outside the footer.
 *
 * The counterpart to FootListenerTrait, for chrome that belongs to the page rather than to its
 * footer — a fixed bar, an overlay — which inside #footer would inherit its small print and its
 * centring.
 *
 * The target method signature must be: (string &$pageBottom): void
 */
trait PageBottomListenerTrait {
	protected function listenPageBottom(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PageBottom',
			function(string &$pageBottom) use ($methodName) {
				$this->$methodName($pageBottom);
			},
			$priority
		);
	}
}
