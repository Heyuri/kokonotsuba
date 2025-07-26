<?php

use Kokonotsuba\Root\Constants\userRole;

class hookDispatcher {
	private array $listeners = [];

	public function addListener(string $event, callable $listener, int $priority = 0): void {
		// Add listener with a specified priority
		$this->listeners[$event][$priority][] = $listener;

		// Ensure listeners are sorted by priority (highest priority first)
		krsort($this->listeners[$event]);
	}

	public function addRoleProtectedListener(string $event, callable $listener, userRole $requiredRole, userRole $currentRole, int $priority = 0, bool $throwException = false): void {
		$wrapped = function (&...$params) use ($listener, $requiredRole, $currentRole, $throwException) {
			if ($currentRole->isLessThan($requiredRole)) {
				// throwing an exception is optional in case you want to have it silently fail / skip
				if($throwException) {
					throw new BoardException('Insufficient role: ' . htmlspecialchars($currentRole->displayRoleName()));
				} else {
					return;
				}
			}

			$listener(...$params);
		};

		$this->addListener($event, $wrapped, $priority);
	}

	public function dispatch(string $event, array $parameters = []): void {
		// Dispatch the event to all listeners, sorted by priority
		if (!isset($this->listeners[$event])) return;

		foreach ($this->listeners[$event] as $listenerGroup) {
			foreach ($listenerGroup as $listener) {
				call_user_func_array($listener, $parameters);
			}
		}
	}
}
