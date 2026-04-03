<?php

namespace Kokonotsuba\module_classes;

/**
 * Shorthand for registering non-role-protected hook listeners.
 *
 * Requires the using class to have $this->moduleContext->moduleEngine available.
 */
trait ListenerTrait {
	protected function listen(string $hookName, callable $callback, int $priority = 0): void {
		$this->moduleContext->moduleEngine->addListener($hookName, $callback, $priority);
	}
}
