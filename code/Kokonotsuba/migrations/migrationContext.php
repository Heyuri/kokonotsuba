<?php

namespace Kokonotsuba\migrations;

use Kokonotsuba\database\databaseConnection;

/**
 * Everything a migration is handed: DDL, raw SQL, live-schema reads, table-name resolution, and
 * for data migrations that read the install's files, where the install is.
 */
class migrationContext {
	/** @param callable(string):void $noteLogger Receives progress messages from up(). */
	public function __construct(
		public readonly sqlRunner $sql,
		public readonly schemaBuilder $schema,
		public readonly schemaInspector $inspector,
		public readonly string $appRoot = '',
		private $noteLogger = null
	) {}

	/** Report progress from a migration that does more than run statements. */
	public function note(string $message): void {
		if ($this->noteLogger !== null) {
			($this->noteLogger)($message);
		}
	}

	/** Resolve a logical table key to its real name. */
	public function table(string $key): string {
		return $this->sql->table($key);
	}

	/** Run a writing statement. {LOGICAL_TABLE} placeholders are expanded first. */
	public function execute(string $query, array $params = []): void {
		$this->sql->run($query, $params);
	}

	public function fetchAll(string $query, array $params = []): array {
		return $this->sql->fetchAll($query, $params);
	}

	public function fetchValue(string $query, array $params = []) {
		return $this->sql->fetchValue($query, $params);
	}

	public function isDryRun(): bool {
		return $this->sql->isDryRun();
	}

	public function getConnection(): databaseConnection {
		return $this->sql->getConnection();
	}
}
