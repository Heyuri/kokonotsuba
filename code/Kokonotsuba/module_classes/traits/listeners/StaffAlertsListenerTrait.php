<?php

namespace Kokonotsuba\module_classes\traits\listeners;

/**
 * Contribute an entry to the staff alerts widget (module/staffAlerts).
 *
 * The widget itself knows nothing about reports, banners or anything else — it dispatches
 * 'StaffAlerts' and renders whatever the enabled modules put in the array. Each listener appends
 * one entry:
 *
 *   ['key' => 'reports', 'label' => 'Reports', 'count' => 3, 'url' => '…', 'title' => '…']
 *
 * where 'count' is how many entries this moderator has not seen yet (0 is fine — the row is still
 * listed, just without an indicator).
 *
 * The target method signature must be: (array &$alerts): void
 */
trait StaffAlertsListenerTrait {
	protected function listenStaffAlerts(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener('StaffAlerts',
			function (array &$alerts) use ($methodName) {
				$this->$methodName($alerts);
			},
			$priority
		);
	}

	/**
	 * The same, gated on the module's own required role, so staff who cannot reach the page an
	 * entry links to are not told there is anything waiting on it.
	 *
	 * Requires the using class to extend abstractModuleAdmin.
	 */
	protected function listenStaffAlertsProtected(string $methodName, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'StaffAlerts',
			function (array &$alerts) use ($methodName) {
				$this->$methodName($alerts);
			},
			$priority
		);
	}
}
