<?php

namespace Kokonotsuba\module_classes;

/**
 * Trait providing a shorthand for action logging in modules.
 *
 * Requires the using class to extend abstractModule (which provides $this->moduleContext).
 */
trait AuditableTrait {
	protected function logAction(string $action, int $boardUid): void {
		$this->moduleContext->actionLoggerService->logAction($action, $boardUid);
	}
}
