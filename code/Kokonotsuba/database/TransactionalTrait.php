<?php

namespace Kokonotsuba\database;

/**
 * Trait providing a convenience wrapper for transactional execution.
 *
 * Requires the using class to have a `transactionManager` property
 * (typically injected via constructor promotion).
 */
trait TransactionalTrait {
	protected function inTransaction(callable $callback): mixed {
		return $this->transactionManager->run($callback);
	}
}
