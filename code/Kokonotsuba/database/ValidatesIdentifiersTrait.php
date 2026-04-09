<?php

namespace Kokonotsuba\database;

trait ValidatesIdentifiersTrait {
	protected static function validateTableName(string $name): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $name)) {
			throw new \InvalidArgumentException("Invalid table name: {$name}");
		}
	}

	protected static function validateTableNames(string ...$names): void {
		foreach ($names as $name) {
			self::validateTableName($name);
		}
	}
}
