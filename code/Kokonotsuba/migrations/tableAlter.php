<?php

namespace Kokonotsuba\migrations;

use InvalidArgumentException;

/** Imperative alterations against one existing table. */
class tableAlter {
	public function __construct(
		private readonly sqlRunner $sql,
		private readonly schemaInspector $inspector,
		private readonly string $tableName
	) {}

	private static function assertColumnName(string $name): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new InvalidArgumentException("Invalid column name: {$name}");
		}
	}

	private static function assertIdentifier(string $name): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new InvalidArgumentException("Invalid identifier: {$name}");
		}
	}

	private function alter(string $clause): self {
		$this->sql->run("ALTER TABLE `{$this->tableName}` {$clause}");
		$this->inspector->forget($this->tableName);

		return $this;
	}

	public function addColumn(string $name, string $definition, string $after = ''): self {
		self::assertColumnName($name);
		$position = '';
		if ($after !== '') {
			self::assertColumnName($after);
			$position = " AFTER `{$after}`";
		}

		return $this->alter("ADD COLUMN `{$name}` {$definition}{$position}");
	}

	public function modifyColumn(string $name, string $definition): self {
		self::assertColumnName($name);

		return $this->alter("MODIFY COLUMN `{$name}` {$definition}");
	}

	public function renameColumn(string $from, string $to, string $definition): self {
		self::assertColumnName($from);
		self::assertColumnName($to);

		return $this->alter("CHANGE COLUMN `{$from}` `{$to}` {$definition}");
	}

	public function dropColumn(string $name): self {
		self::assertColumnName($name);

		return $this->alter("DROP COLUMN `{$name}`");
	}

	public function addIndex(string $name, array $columns, string $type = 'INDEX'): self {
		self::assertIdentifier($name);
		if (!in_array(strtoupper($type), ['INDEX', 'UNIQUE', 'FULLTEXT'], true)) {
			throw new InvalidArgumentException("Invalid index type: {$type}");
		}
		foreach ($columns as $column) {
			self::assertColumnName($column);
		}

		$members = implode(', ', array_map(fn (string $c): string => "`{$c}`", $columns));
		$keyword = strtoupper($type) === 'INDEX' ? 'INDEX' : strtoupper($type).' INDEX';

		return $this->alter("ADD {$keyword} `{$name}` ({$members})");
	}

	public function dropIndex(string $name): self {
		self::assertIdentifier($name);

		return $this->alter("DROP INDEX `{$name}`");
	}

	public function dropForeignKey(string $name): self {
		self::assertIdentifier($name);

		return $this->alter("DROP FOREIGN KEY `{$name}`");
	}
}
