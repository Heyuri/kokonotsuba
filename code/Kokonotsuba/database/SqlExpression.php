<?php

namespace Kokonotsuba\database;

/**
 * Represents a raw SQL expression that should be embedded directly into a query
 * rather than being bound as a parameter value.
 *
 * Usage: new SqlExpression('CURRENT_TIMESTAMP') or SqlExpression::now()
 */
class SqlExpression {
	use ValidatesIdentifiersTrait;

	public function __construct(
		public readonly string $expression
	) {}

	public function __toString(): string {
		return $this->expression;
	}

	public static function now(): self {
		return new self('CURRENT_TIMESTAMP');
	}

	public static function increment(string $column, int $amount = 1): self {
		self::validateTableName($column);
		return new self("{$column} + {$amount}");
	}

	public static function decrement(string $column, int $amount = 1): self {
		self::validateTableName($column);
		return new self("{$column} - {$amount}");
	}
}
