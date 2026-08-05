<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Contribute a destination to the sticky staff nav (module/staffNav).
 *
 * The nav is built from data rather than the HTML 'LinksAboveBar' produces, because it needs more
 * than a link: which drop-up a destination belongs in, and whether anything is waiting there.
 * Each listener appends one entry:
 *
 *   ['key' => 'reports', 'label' => 'Reports', 'url' => '…', 'title' => '…',
 *    'group' => 'moderation', 'count' => 3]
 *
 * 'group' is a bare key translated by the nav ('staffnav_group_{key}'); leave it empty to sit at
 * the top level of the bar. 'count' is optional and renders as a (n) indicator.
 *
 * Most admin modules never need this trait: registerLinksAboveBarHook() already registers a nav
 * entry alongside the admin bar link, and takes the group as its last argument.
 *
 * The target method signature must be: (array &$entries): void
 */
trait StaffNavListenerTrait {
	protected function listenStaffNav(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('StaffNavLinks',
			function (array &$entries) use ($methodName) {
				$this->$methodName($entries);
			},
			$priority
		);
	}

	/**
	 * The same, gated on the module's own required role, so staff who cannot reach the page are
	 * not offered a link to it.
	 *
	 * Requires the using class to extend abstractModuleAdmin.
	 */
	protected function listenStaffNavProtected(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'StaffNavLinks',
			function (array &$entries) use ($methodName) {
				$this->$methodName($entries);
			},
			$priority
		);
	}
}
