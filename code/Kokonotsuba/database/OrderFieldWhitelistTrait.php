<?php

namespace Kokonotsuba\database;

/**
 * Trait for validating ORDER BY fields against an allowlist.
 *
 * Requires the using class to have an `$allowedOrderFields` array property.
 */
trait OrderFieldWhitelistTrait {
	protected function validateOrderField(string $field, string $default): string {
		return in_array($field, $this->allowedOrderFields, true) ? $field : $default;
	}

	protected function isValidOrderField(string $field): bool {
		return in_array($field, $this->allowedOrderFields, true);
	}
}
