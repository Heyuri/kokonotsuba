<?php

namespace Kokonotsuba\module_classes\traits;

use Kokonotsuba\action_log\actionType;
use Kokonotsuba\action_log\actionTypeDefinition;

/**
 * Trait providing a shorthand for action logging in modules.
 *
 * A module with events of its own keeps them in an actionTypeDefinition enum and registers it with
 * registerActionTypes() during initialize(), which puts a checkbox per case on the action log
 * filter form. Register from moduleMain where there is one: moduleAdmin only initializes for staff
 * at its required role.
 *
 * Requires the using class to extend abstractModule (which provides $this->moduleContext).
 */
trait AuditableTrait {
	protected function logAction(string $action, int $boardUid, actionTypeDefinition|string $type = actionType::OTHER, bool $isAnon = false): void {
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

	/**
	 * Declare every case of this module's action type enum.
	 *
	 * @param class-string<actionTypeDefinition> $enumClass
	 */
	protected function registerActionTypes(string $enumClass): void {
		$this->moduleContext->actionLoggerService->registerTypes($enumClass);
	}

	/** Declare a single action type of this module's own. */
	protected function registerActionType(string $key, string $label, string $group = 'tool', bool $default = true): void {
		$this->moduleContext->actionLoggerService->registerType($key, $label, $group, $default);
	}
}
