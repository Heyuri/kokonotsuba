<?php

namespace Kokonotsuba\migrations;

/**
 * One schema or data change. Migration files `return` an anonymous subclass of this.
 *
 * MariaDB commits implicitly on DDL, so a migration that runs several structural statements
 * cannot be rolled back as a unit. Keep structural migrations to one change each.
 */
abstract class migration {
	abstract public function up(migrationContext $ctx): void;

	public function down(migrationContext $ctx): void {
		throw new irreversibleMigrationException(static::class);
	}

	public function description(): string {
		return '';
	}

	/** Data-only migrations get a real transaction; DDL ones must return false. */
	public function isTransactional(): bool {
		return true;
	}

	/**
	 * Whether up() only declares tables, and so can be re-run against an existing database to
	 * add whatever it is missing. True for the baseline, which is how installs predating the
	 * ledger are brought up to it.
	 */
	public function isReconcilable(): bool {
		return false;
	}

	/**
	 * Whether the database already shows this migration's effect, for stamping installs that
	 * predate the ledger. Return null when it cannot be determined.
	 */
	public function detect(migrationContext $ctx): ?bool {
		return null;
	}
}
