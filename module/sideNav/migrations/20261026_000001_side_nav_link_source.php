<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** What an automatic link stands for: 'board:{uid}', 'home', 'overboard' or 'hide'. NULL for a plain link. */
return new class extends migration {
	public function description(): string {
		return 'Side nav link source';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('SIDE_NAV_LINK_TABLE')
			->addColumn('source', 'VARCHAR(32) DEFAULT NULL', 'url');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('SIDE_NAV_LINK_TABLE')
			->dropColumn('source');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('SIDE_NAV_LINK_TABLE', 'source');
	}
};
