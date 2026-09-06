<?php

namespace Kokonotsuba\migrations;

use Kokonotsuba\database\databaseConnection;

/** Reads the live schema out of INFORMATION_SCHEMA. Always live, never suppressed by dry runs. */
class schemaInspector {
	private array $columnCache = [];

	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly string $databaseName
	) {}

	/** Drop memoised reads after a statement changes the schema. */
	public function forget(?string $table = null): void {
		if ($table === null) {
			$this->columnCache = [];
		} else {
			unset($this->columnCache[$table]);
		}
	}

	public function tableExists(string $table): bool {
		return (int)$this->databaseConnection->fetchValue(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
			 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table',
			[':db' => $this->databaseName, ':table' => $table]
		) > 0;
	}

	/** @return list<string> */
	public function getTableNames(): array {
		$rows = $this->databaseConnection->fetchAllAsArray(
			'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db',
			[':db' => $this->databaseName]
		);

		return array_column($rows, 'TABLE_NAME');
	}

	/** @return array<string, array{type: string, nullable: bool, default: ?string, extra: string}> */
	public function getColumns(string $table): array {
		if (isset($this->columnCache[$table])) {
			return $this->columnCache[$table];
		}

		$rows = $this->databaseConnection->fetchAllAsArray(
			'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table
			 ORDER BY ORDINAL_POSITION',
			[':db' => $this->databaseName, ':table' => $table]
		);

		$columns = [];
		foreach ($rows as $row) {
			$columns[$row['COLUMN_NAME']] = [
				'type' => $row['COLUMN_TYPE'],
				'nullable' => $row['IS_NULLABLE'] === 'YES',
				'default' => $row['COLUMN_DEFAULT'],
				'extra' => $row['EXTRA'],
			];
		}

		return $this->columnCache[$table] = $columns;
	}

	public function columnExists(string $table, string $column): bool {
		return isset($this->getColumns($table)[$column]);
	}

	/** @return list<string> */
	public function getIndexNames(string $table): array {
		$rows = $this->databaseConnection->fetchAllAsArray(
			'SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table',
			[':db' => $this->databaseName, ':table' => $table]
		);

		return array_column($rows, 'INDEX_NAME');
	}

	public function indexExists(string $table, string $index): bool {
		return in_array($index, $this->getIndexNames($table), true);
	}

	/** @return list<string> */
	public function getForeignKeyNames(string $table): array {
		$rows = $this->databaseConnection->fetchAllAsArray(
			'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
			 WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
			[':db' => $this->databaseName, ':table' => $table]
		);

		return array_column($rows, 'CONSTRAINT_NAME');
	}

	public function foreignKeyExists(string $table, string $constraint): bool {
		return in_array($constraint, $this->getForeignKeyNames($table), true);
	}
}
