<?php

namespace Kokonotsuba\migrations;

/**
 * DDL surface handed to migrations.
 *
 * In reconcile mode only createTable() does anything: a missing table is created, and an
 * existing one gains whatever columns, indexes and constraints the blueprint declares and the
 * database lacks. Nothing is ever dropped or modified. That is what brings an install predating
 * the ledger up to the baseline, and what `doctor` uses to report drift.
 *
 * Reconciliation matches indexes and constraints by name, so an index carrying the same columns
 * under a different name reads as missing.
 */
class schemaBuilder {
	public const MODE_APPLY = 'apply';
	public const MODE_RECONCILE = 'reconcile';

	/** @var list<string> Human-readable notes about what reconciliation found. */
	private array $findings = [];

	public function __construct(
		private readonly sqlRunner $sql,
		private readonly schemaInspector $inspector,
		private readonly string $mode = self::MODE_APPLY
	) {}

	public function isReconciling(): bool {
		return $this->mode === self::MODE_RECONCILE;
	}

	/** @return list<string> */
	public function getFindings(): array {
		return $this->findings;
	}

	private function skip(string $operation): void {
		$this->findings[] = "skipped in reconcile mode: {$operation}";
	}

	/** @param callable(tableBlueprint):void $definer */
	public function createTable(string $tableKey, callable $definer): void {
		$blueprint = new tableBlueprint();
		$definer($blueprint);

		$tableName = $this->sql->table($tableKey);

		if (!$this->isReconciling()) {
			$this->sql->run($blueprint->toCreateSql($tableName, $this->sql));
			$this->inspector->forget($tableName);

			return;
		}

		if (!$this->inspector->tableExists($tableName)) {
			$this->findings[] = "missing table {$tableName}";
			$this->sql->run($blueprint->toCreateSql($tableName, $this->sql));
			$this->inspector->forget($tableName);

			return;
		}

		$this->reconcileExistingTable($tableName, $blueprint);
	}

	private function reconcileExistingTable(string $tableName, tableBlueprint $blueprint): void {
		$existingColumns = $this->inspector->getColumns($tableName);
		$previous = '';

		foreach ($blueprint->getColumns() as $column => $definition) {
			if (isset($existingColumns[$column])) {
				$previous = $column;
				continue;
			}

			$this->findings[] = "missing column {$tableName}.{$column}";
			$after = $previous !== '' ? " AFTER `{$previous}`" : '';
			$this->sql->run("ALTER TABLE `{$tableName}` ADD COLUMN `{$column}` {$definition}{$after}");
			$this->inspector->forget($tableName);
			$previous = $column;
		}

		foreach (array_keys($blueprint->getIndexes()) as $index) {
			if ($this->inspector->indexExists($tableName, $index)) {
				continue;
			}

			$this->findings[] = "missing index {$tableName}.{$index}";
			$this->sql->run("ALTER TABLE `{$tableName}` ADD ".$blueprint->indexClause($index));
		}

		foreach (array_keys($blueprint->getForeignKeys()) as $constraint) {
			if ($this->inspector->foreignKeyExists($tableName, $constraint)) {
				continue;
			}

			$this->findings[] = "missing constraint {$tableName}.{$constraint}";
			$this->sql->run("ALTER TABLE `{$tableName}` ADD ".$blueprint->foreignKeyClause($constraint, $this->sql));
		}
	}

	public function dropTable(string $tableKey): void {
		$tableName = $this->sql->table($tableKey);

		if ($this->isReconciling()) {
			$this->skip("DROP TABLE {$tableName}");

			return;
		}

		$this->sql->run("DROP TABLE IF EXISTS `{$tableName}`");
		$this->inspector->forget($tableName);
	}

	public function renameTable(string $fromTableKey, string $toTableKey): void {
		$from = $this->sql->table($fromTableKey);
		$to = $this->sql->table($toTableKey);

		if ($this->isReconciling()) {
			$this->skip("RENAME TABLE {$from} TO {$to}");

			return;
		}

		$this->sql->run("RENAME TABLE `{$from}` TO `{$to}`");
		$this->inspector->forget();
	}

	public function table(string $tableKey): tableAlter {
		return new tableAlter($this->sql, $this->inspector, $this->sql->table($tableKey));
	}

	public function tableExists(string $tableKey): bool {
		return $this->inspector->tableExists($this->sql->table($tableKey));
	}

	public function columnExists(string $tableKey, string $column): bool {
		return $this->inspector->columnExists($this->sql->table($tableKey), $column);
	}

	public function indexExists(string $tableKey, string $index): bool {
		return $this->inspector->indexExists($this->sql->table($tableKey), $index);
	}
}
