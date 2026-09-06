<?php

namespace Kokonotsuba\migrations;

use RuntimeException;

class irreversibleMigrationException extends RuntimeException {
	public function __construct(string $migration) {
		parent::__construct("Migration {$migration} declares no down().");
	}
}
