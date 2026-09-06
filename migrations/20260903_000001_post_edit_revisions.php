<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * What a post said before each edit.
 *
 * One row per edit, holding the values as they stood the moment before it, so staff can read
 * back what a poster changed and put any of it back. edited_by is the staff account behind the
 * edit and is NULL when the poster made it with their own password, which is the difference
 * between the two kinds of edit; the account is only a label, so it is dropped to NULL rather
 * than taking the history with it when an account goes.
 */
return new class extends migration {
	public function description(): string {
		return 'Post edit revision history';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('POST_EDIT_REVISION_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('post_uid', 'INT(11) NOT NULL');
			$t->column('boardUID', 'INT(11) NOT NULL');
			$t->column('edited_by', 'INT(11) DEFAULT NULL');
			$t->column('edited_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('name', 'VARCHAR(255) DEFAULT NULL');
			$t->column('email', 'VARCHAR(255) DEFAULT NULL');
			$t->column('sub', 'VARCHAR(255) DEFAULT NULL');
			$t->column('com', 'TEXT DEFAULT NULL');
			$t->column('tag', 'VARCHAR(255) DEFAULT NULL');
			$t->primary('id');
			$t->index('idx_post_edit_revisions_post_uid', ['post_uid']);
			$t->index('fk_post_edit_revisions_edited_by', ['edited_by']);
			$t->foreign('fk_post_edit_revisions_edited_by', 'edited_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->execute('DROP TABLE IF EXISTS {POST_EDIT_REVISION_TABLE}');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('POST_EDIT_REVISION_TABLE');
	}
};
