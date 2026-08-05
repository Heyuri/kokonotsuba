<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Emit markup as the first thing inside <body>, above the board list and the admin bar.
 *
 * The counterpart to PageBottomListenerTrait, and not the same as the older 'PageTop' hook: that
 * one fills the banner, which sits inside the page header beneath both of those. This is for
 * chrome that belongs over the whole page rather than inside its header — a sticky bar, say.
 *
 * The target method signature must be: (string &$pageStart): void
 */
trait PageStartListenerTrait {
	protected function listenPageStart(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('PageStart',
			function(string &$pageStart) use ($methodName) {
				$this->$methodName($pageStart);
			},
			$priority
		);
	}
}
