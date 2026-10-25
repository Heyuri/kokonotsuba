<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Emit the side navigation, after the nav section ({$SIDE_NAV_HOOK}).
 *
 * The target method signature must be: (string &$sideNav): void
 */
trait SideNavListenerTrait {
	protected function listenSideNav(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('SideNav',
			function(string &$sideNav) use ($methodName) {
				$this->$methodName($sideNav);
			},
			$priority
		);
	}
}
