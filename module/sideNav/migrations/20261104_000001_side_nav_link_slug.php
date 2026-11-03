<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** A custom link's slug, drawn as "/slug/ - " beside the label when the dirs toggle is on. */
return new class extends migration {
	public function description(): string {
		return 'Side nav link slug';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('SIDE_NAV_LINK_TABLE')
			->addColumn('slug', "VARCHAR(64) NOT NULL DEFAULT ''", 'url');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('SIDE_NAV_LINK_TABLE')
			->dropColumn('slug');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('SIDE_NAV_LINK_TABLE', 'slug');
	}
};
