<?php

namespace Kokonotsuba\migrations;

use InvalidArgumentException;

/**
 * Declarative table definition. Emits CREATE TABLE, and is readable enough for the reconciler
 * to work out what an older database is missing.
 *
 * Column and index definitions are author-written SQL fragments, never request data.
 */
class tableBlueprint {
	/** @var array<string, string> column name => type/attribute fragment */
	private array $columns = [];

	/** @var list<string> */
	private array $primaryKey = [];

	/** @var array<string, array{type: string, columns: list<string>}> */
	private array $indexes = [];

	/** @var array<string, array{columns: list<string>, tableKey: string, references: list<string>, onDelete: string, onUpdate: string}> */
	private array $foreignKeys = [];

	private string $engine = 'InnoDB';
	private ?string $charset = null;

	private static function assertColumnName(string $name): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new InvalidArgumentException("Invalid column name: {$name}");
		}
	}

	/** Index members may carry a prefix length and a direction, e.g. md5(32), is_op DESC. */
	private const INDEX_COLUMN_PATTERN = '/^([a-zA-Z_][a-zA-Z0-9_]*)(\(\d+\))?(?: (ASC|DESC))?$/i';

	private static function assertIndexColumn(string $expression): void {
		if (!preg_match(self::INDEX_COLUMN_PATTERN, $expression)) {
			throw new InvalidArgumentException("Invalid index column: {$expression}");
		}
	}

	private static function assertIdentifier(string $name): void {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new InvalidArgumentException("Invalid identifier: {$name}");
		}
	}

	public function column(string $name, string $definition): self {
		self::assertColumnName($name);
		$this->columns[$name] = trim($definition);

		return $this;
	}

	public function primary(string ...$columns): self {
		foreach ($columns as $column) {
			self::assertColumnName($column);
		}
		$this->primaryKey = array_values($columns);

		return $this;
	}

	public function index(string $name, array $columns, string $using = ''): self {
		return $this->addIndex('KEY', $name, $columns, $using);
	}

	public function unique(string $name, array $columns, string $using = ''): self {
		return $this->addIndex('UNIQUE KEY', $name, $columns, $using);
	}

	public function fulltext(string $name, array $columns): self {
		return $this->addIndex('FULLTEXT KEY', $name, $columns);
	}

	private function addIndex(string $type, string $name, array $columns, string $using = ''): self {
		self::assertIdentifier($name);
		foreach ($columns as $column) {
			self::assertIndexColumn($column);
		}
		if ($using !== '' && !in_array(strtoupper($using), ['BTREE', 'HASH'], true)) {
			throw new InvalidArgumentException("Invalid index method: {$using}");
		}

		$this->indexes[$name] = [
			'type' => $type,
			'columns' => array_values($columns),
			'using' => strtoupper($using),
		];

		return $this;
	}

	/**
	 * @param string|list<string> $columns
	 * @param string|list<string> $references Column(s) on the referenced table.
	 */
	public function foreign(
		string $name,
		string|array $columns,
		string $referencedTableKey,
		string|array $references,
		string $onDelete = '',
		string $onUpdate = ''
	): self {
		self::assertIdentifier($name);
		self::assertIdentifier($referencedTableKey);

		$columns = (array)$columns;
		$references = (array)$references;
		foreach ([...$columns, ...$references] as $column) {
			self::assertColumnName($column);
		}

		foreach (['onDelete' => $onDelete, 'onUpdate' => $onUpdate] as $label => $action) {
			if ($action !== '' && !in_array(strtoupper($action), ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'], true)) {
				throw new InvalidArgumentException("Invalid {$label} action: {$action}");
			}
		}

		$this->foreignKeys[$name] = [
			'columns' => array_values($columns),
			'tableKey' => $referencedTableKey,
			'references' => array_values($references),
			'onDelete' => strtoupper($onDelete),
			'onUpdate' => strtoupper($onUpdate),
		];

		return $this;
	}

	public function engine(string $engine): self {
		self::assertIdentifier($engine);
		$this->engine = $engine;

		return $this;
	}

	public function charset(string $charset): self {
		self::assertIdentifier($charset);
		$this->charset = $charset;

		return $this;
	}

	/** @return array<string, string> */
	public function getColumns(): array {
		return $this->columns;
	}

	/** @return array<string, array{type: string, columns: list<string>}> */
	public function getIndexes(): array {
		return $this->indexes;
	}

	/** @return array<string, array> */
	public function getForeignKeys(): array {
		return $this->foreignKeys;
	}

	private static function quote(string $identifier): string {
		return '`'.$identifier.'`';
	}

	/** Quote an index member, keeping any prefix length and direction outside the backticks. */
	private static function quoteIndexColumn(string $expression): string {
		if (!preg_match(self::INDEX_COLUMN_PATTERN, $expression, $matches)) {
			throw new InvalidArgumentException("Invalid index column: {$expression}");
		}

		return self::quote($matches[1])
			.($matches[2] ?? '')
			.(($matches[3] ?? '') !== '' ? ' '.strtoupper($matches[3]) : '');
	}

	public function indexClause(string $name): string {
		$index = $this->indexes[$name];
		$columns = implode(', ', array_map([self::class, 'quoteIndexColumn'], $index['columns']));
		$using = ($index['using'] ?? '') !== '' ? " USING {$index['using']}" : '';

		return "{$index['type']} ".self::quote($name)." ({$columns}){$using}";
	}

	public function foreignKeyClause(string $name, sqlRunner $sql): string {
		$key = $this->foreignKeys[$name];
		$columns = implode(', ', array_map([self::class, 'quote'], $key['columns']));
		$references = implode(', ', array_map([self::class, 'quote'], $key['references']));
		$referencedTable = self::quote($sql->table($key['tableKey']));

		$clause = 'CONSTRAINT '.self::quote($name)." FOREIGN KEY ({$columns}) REFERENCES {$referencedTable} ({$references})";
		if ($key['onDelete'] !== '') {
			$clause .= " ON DELETE {$key['onDelete']}";
		}
		if ($key['onUpdate'] !== '') {
			$clause .= " ON UPDATE {$key['onUpdate']}";
		}

		return $clause;
	}

	public function toCreateSql(string $tableName, sqlRunner $sql): string {
		if ($this->columns === []) {
			throw new InvalidArgumentException("Table {$tableName} defines no columns.");
		}

		$clauses = [];
		foreach ($this->columns as $name => $definition) {
			$clauses[] = self::quote($name).' '.$definition;
		}

		if ($this->primaryKey !== []) {
			$clauses[] = 'PRIMARY KEY ('.implode(', ', array_map([self::class, 'quote'], $this->primaryKey)).')';
		}

		foreach (array_keys($this->indexes) as $name) {
			$clauses[] = $this->indexClause($name);
		}

		foreach (array_keys($this->foreignKeys) as $name) {
			$clauses[] = $this->foreignKeyClause($name, $sql);
		}

		$suffix = "ENGINE={$this->engine}";
		if ($this->charset !== null) {
			$suffix .= " DEFAULT CHARSET={$this->charset}";
		}

		return 'CREATE TABLE IF NOT EXISTS '.self::quote($tableName)." (\n\t"
			.implode(",\n\t", $clauses)
			."\n) {$suffix}";
	}
}
