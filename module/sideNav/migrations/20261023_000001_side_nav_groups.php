<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\tableBlueprint;
use Kokonotsuba\migrations\migrationContext;

/** Side nav sections. A 'boards' group lists the listed boards instead of its own links. */
return new class extends migration {
	public function description(): string {
		return 'Side nav groups';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('SIDE_NAV_GROUP_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('title', 'VARCHAR(255) NOT NULL');
			$t->column('kind', "VARCHAR(16) NOT NULL DEFAULT 'links'");
			$t->column('sort_order', 'INT(11) NOT NULL DEFAULT 0');
			$t->primary('id');
			$t->index('idx_side_nav_group_sort', ['sort_order']);
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('SIDE_NAV_GROUP_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('SIDE_NAV_GROUP_TABLE');
	}
};
