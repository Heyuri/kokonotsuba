<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\action_log\actionType;

/**
 * Trait providing a shorthand for action logging in modules.
 *
 * A module with an event of its own can add a type for it with registerActionType() during
 * initialize(), which puts a checkbox for it on the action log filter form.
 *
 * Requires the using class to extend abstractModule (which provides $this->moduleContext).
 */
trait AuditableTrait {
	protected function logAction(string $action, int $boardUid, actionType|string $type = actionType::OTHER, bool $isAnon = false): void {
		$this->moduleContext->actionLoggerService->logAction($action, $boardUid, $type, $isAnon);
	}

	/**
	 * Say where a kind of reference in this module's log lines points, making those entries
	 * clickable in the action log. Build the reference itself with actionLogReferences::reference().
	 *
	 * @param callable(string, int): ?string $resolver Given the id and the entry's board UID.
	 */
	protected function registerActionReference(string $kind, callable $resolver): void {
		$this->moduleContext->actionLoggerService->registerReference($kind, $resolver);
	}

	/** Declare an action type of this module's own. */
	protected function registerActionType(string $key, string $label, string $group = 'tool', bool $default = true): void {
		$this->moduleContext->actionLoggerService->registerType($key, $label, $group, $default);
	}
}
