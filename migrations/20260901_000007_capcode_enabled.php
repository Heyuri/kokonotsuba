<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Whether a user capcode is in effect.
 *
 * A capcode was previously either present or deleted, so retiring one meant losing the row along
 * with the record of who added it and when. This flag switches a capcode off instead: the
 * tripcode still renders, the badge does not, and the entry stays on the admin page to be
 * switched back on. Existing capcodes are enabled, which is what they were before the flag.
 */
return new class extends migration {
	public function description(): string {
		return 'Enable/disable flag on user capcodes';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('CAPCODE_TABLE')
			->addColumn('is_enabled', 'TINYINT(1) NOT NULL DEFAULT 1', 'cap_text');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('CAPCODE_TABLE')
			->dropColumn('is_enabled');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('CAPCODE_TABLE', 'is_enabled');
	}
};
