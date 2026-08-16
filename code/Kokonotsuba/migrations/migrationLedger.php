<?php

namespace Kokonotsuba\migrations;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\ValidatesIdentifiersTrait;

/**
 * The applied-migration record. Created by the runner rather than by a migration, since it has
 * to exist before anything can be recorded.
 */
class migrationLedger {
	use ValidatesIdentifiersTrait;

	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly string $table
	) {
		self::validateTableName($table);
	}

	public function ensureExists(): void {
		$this->databaseConnection->execute(
			"CREATE TABLE IF NOT EXISTS `{$this->table}` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`namespace` VARCHAR(64) NOT NULL DEFAULT 'core',
				`version` VARCHAR(32) NOT NULL,
				`name` VARCHAR(191) NOT NULL,
				`checksum` CHAR(64) NOT NULL,
				`applied_at` DATETIME NOT NULL,
				`execution_ms` INT UNSIGNED NOT NULL DEFAULT 0,
				`koko_version` VARCHAR(32) NOT NULL DEFAULT '',
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_migration_namespace_version` (`namespace`, `version`)
			) ENGINE=InnoDB"
		);
	}

	public function exists(): bool {
		try {
			$this->databaseConnection->fetchValue("SELECT 1 FROM `{$this->table}` LIMIT 1");

			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	/** @return array<string, array<string, array>> namespace => version => row */
	public function all(): array {
		$rows = $this->databaseConnection->fetchAllAsArray(
			"SELECT * FROM `{$this->table}` ORDER BY `namespace`, `version`"
		);

		$byNamespace = [];
		foreach ($rows as $row) {
			$byNamespace[$row['namespace']][$row['version']] = $row;
		}

		return $byNamespace;
	}

	public function isApplied(string $namespace, string $version): bool {
		return (int)$this->databaseConnection->fetchValue(
			"SELECT COUNT(*) FROM `{$this->table}` WHERE `namespace` = :namespace AND `version` = :version",
			[':namespace' => $namespace, ':version' => $version]
		) > 0;
	}

	public function head(string $namespace): ?string {
		$version = $this->databaseConnection->fetchValue(
			"SELECT MAX(`version`) FROM `{$this->table}` WHERE `namespace` = :namespace",
			[':namespace' => $namespace]
		);

		return $version !== false && $version !== null ? (string)$version : null;
	}

	public function record(discoveredMigration $migration, int $executionMs, string $kokoVersion): void {
		$this->databaseConnection->execute(
			"INSERT INTO `{$this->table}`
				(`namespace`, `version`, `name`, `checksum`, `applied_at`, `execution_ms`, `koko_version`)
			 VALUES (:namespace, :version, :name, :checksum, NOW(), :ms, :kokoVersion)",
			[
				':namespace' => $migration->namespace,
				':version' => $migration->version,
				':name' => $migration->name,
				':checksum' => $migration->checksum(),
				':ms' => $executionMs,
				':kokoVersion' => $kokoVersion,
			]
		);
	}

	public function forget(string $namespace, string $version): void {
		$this->databaseConnection->execute(
			"DELETE FROM `{$this->table}` WHERE `namespace` = :namespace AND `version` = :version",
			[':namespace' => $namespace, ':version' => $version]
		);
	}
}
