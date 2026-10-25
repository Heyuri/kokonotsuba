<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\tableBlueprint;
use Kokonotsuba\migrations\migrationContext;

/** Side nav links, each in one group; deleting a group deletes its links. */
return new class extends migration {
	public function description(): string {
		return 'Side nav links';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('SIDE_NAV_LINK_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('label', 'VARCHAR(255) NOT NULL');
			$t->column('url', 'VARCHAR(2048) NOT NULL');
			$t->column('group_id', 'INT(11) NOT NULL');
			$t->column('sort_order', 'INT(11) NOT NULL DEFAULT 0');
			$t->primary('id');
			$t->index('idx_side_nav_link_group', ['group_id', 'sort_order']);
			$t->foreign('fk_side_nav_link_group', 'group_id', 'SIDE_NAV_GROUP_TABLE', 'id', 'CASCADE');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('SIDE_NAV_LINK_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('SIDE_NAV_LINK_TABLE');
	}
};
