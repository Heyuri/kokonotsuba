<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** An automatic link the admin hid; deleting one would only bring it back. */
return new class extends migration {
	public function description(): string {
		return 'Side nav link hidden flag';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('SIDE_NAV_LINK_TABLE')
			->addColumn('hidden', 'TINYINT(1) NOT NULL DEFAULT 0', 'source');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('SIDE_NAV_LINK_TABLE')
			->dropColumn('hidden');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('SIDE_NAV_LINK_TABLE', 'hidden');
	}
};
