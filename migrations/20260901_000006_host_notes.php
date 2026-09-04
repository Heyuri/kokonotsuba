<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * Staff notes filed against a host rather than a post.
 *
 * A note's ip_pattern is either an exact address or a ban-style wildcard range, and is_wildcard
 * records which so the exact ones can be looked up by index and only the handful of ranges get
 * matched in PHP. Notes are global: a host is not a board.
 */
return new class extends migration {
	public function description(): string {
		return 'Host notes table';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('HOST_NOTE_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('ip_pattern', 'VARCHAR(255) NOT NULL');
			$t->column('is_wildcard', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('note_submitted', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('added_by', 'INT(11) DEFAULT NULL');
			$t->column('note_text', 'TEXT NOT NULL');
			$t->primary('id');
			$t->index('idx_host_notes_ip_pattern', ['ip_pattern']);
			$t->index('idx_host_notes_is_wildcard', ['is_wildcard']);
			$t->index('fk_host_notes_added_by', ['added_by']);
			$t->foreign('fk_host_notes_added_by', 'added_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->execute('DROP TABLE IF EXISTS {HOST_NOTE_TABLE}');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('HOST_NOTE_TABLE');
	}
};
