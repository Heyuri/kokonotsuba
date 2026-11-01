<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * Front page pages (FAQ, rules, ...), reached by slug. A published page can be left out of the
 * navigation and still be linked to; a draft is staff-only.
 */
return new class extends migration {
	public function description(): string {
		return 'Front page pages';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('FRONT_PAGES_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('slug', 'VARCHAR(64) NOT NULL');
			$t->column('title', 'VARCHAR(255) NOT NULL');
			$t->column('body', 'MEDIUMTEXT NOT NULL');
			$t->column('is_published', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('show_in_nav', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('nav_order', 'INT(11) NOT NULL DEFAULT 0');
			$t->column('author_id', 'INT(11) DEFAULT NULL');
			$t->column('created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('updated_at', 'DATETIME DEFAULT NULL');
			$t->primary('id');
			$t->unique('uq_front_pages_slug', ['slug']);
			$t->index('idx_front_pages_nav', ['is_published', 'show_in_nav', 'nav_order']);
			$t->index('fk_front_pages_author', ['author_id']);
			$t->foreign('fk_front_pages_author', 'author_id', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('FRONT_PAGES_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('FRONT_PAGES_TABLE');
	}
};
