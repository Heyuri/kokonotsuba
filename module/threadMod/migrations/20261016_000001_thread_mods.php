<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\tableBlueprint;
use Kokonotsuba\migrations\migrationContext;

/**
 * Who moderates a thread besides its OP.
 *
 * One row per thread and browser token. The OP needs no row: ownership is read off the opening
 * post (its token hash and password) unless a transfer has written an owner row here, which then
 * takes precedence. A revoked grant keeps its row with revoked_at set, so a re-grant reopens it.
 */
return new class extends migration {
	public function description(): string {
		return 'Thread mod grants';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('THREAD_MOD_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('thread_uid', 'VARCHAR(255) NOT NULL');
			// visitorTokenSigner::TOKEN_HASH_LENGTH hex chars, as posts.visitor_token_hash holds
			$t->column('visitor_token_hash', 'VARCHAR(64) NOT NULL');
			// threadRole value: 1 mod, 2 owner
			$t->column('role', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('granted_from_post_uid', 'INT(11) DEFAULT NULL');
			$t->column('granted_by_hash', 'VARCHAR(64) DEFAULT NULL');
			$t->column('granted_at', 'DATETIME NOT NULL');
			$t->column('revoked_at', 'DATETIME DEFAULT NULL');
			$t->column('revoked_by_hash', 'VARCHAR(64) DEFAULT NULL');
			$t->column('revoked_by_account', 'INT(11) DEFAULT NULL');
			$t->primary('id');
			$t->unique('uq_thread_token', ['thread_uid', 'visitor_token_hash']);
			$t->index('idx_thread_mod_role', ['thread_uid', 'role', 'revoked_at']);
			$t->index('idx_thread_mod_token', ['visitor_token_hash']);
			$t->foreign('fk_thread_mod_thread', 'thread_uid', 'THREAD_TABLE', 'thread_uid', 'CASCADE');
			$t->foreign('fk_thread_mod_post', 'granted_from_post_uid', 'POST_TABLE', 'post_uid', 'SET NULL');
			$t->foreign('fk_thread_mod_account', 'revoked_by_account', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('THREAD_MOD_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('THREAD_MOD_TABLE');
	}
};
