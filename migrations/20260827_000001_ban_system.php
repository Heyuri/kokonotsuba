<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/**
 * Tables for the database-backed ban system.
 *
 * Bans used to live in flat CSV files (bans.log.txt per board, globalbans.log), which had room
 * for an IP, a start, an expiry and a reason and nothing else. Checkpoints, seen state, appeals,
 * revocation history and permanent bans all need somewhere to live, so they move here.
 *
 * Existing ban files are brought across by Utilities/ban-import-cli.php.
 */
return new class extends migration {
	public function description(): string {
		return 'Ban and ban appeal tables';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function isReconcilable(): bool {
		return true;
	}

	public function up(migrationContext $ctx): void {
		$schema = $ctx->schema;

		$schema->createTable('BAN_TABLE', function (tableBlueprint $t): void {
			$t->column('ban_id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('ip_pattern', 'VARCHAR(64) NOT NULL');
			// Wildcard patterns cannot be matched by an index, so they are flagged and matched in
			// PHP; exact patterns stay an indexed equality lookup.
			$t->column('is_wildcard', 'TINYINT(1) NOT NULL DEFAULT 0');
			// The fingerprint of the browser's token, not the token: matching recomputes it from
			// the cookie on each request, so nothing server-side has to hold the token itself.
			$t->column('visitor_token_hash', 'VARCHAR(32) DEFAULT NULL');
			$t->column('post_uid', 'INT(11) DEFAULT NULL');
			$t->column('reason', 'TEXT DEFAULT NULL');
			// Comma-separated checkpoint keys this ban blocks; empty for a warning.
			$t->column('checkpoints', 'VARCHAR(255) NOT NULL DEFAULT \'\'');
			$t->column('is_warning', 'TINYINT(1) NOT NULL DEFAULT 0');
			$t->column('filed_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			// NULL means permanent.
			$t->column('expires_at', 'DATETIME DEFAULT NULL');
			$t->column('filed_by', 'INT(11) DEFAULT NULL');
			$t->column('seen_at', 'DATETIME DEFAULT NULL');
			// NULL until seen, then 1 when the visitor carried a token cookie and 0 when they did not.
			$t->column('seen_cookies', 'TINYINT(1) DEFAULT NULL');
			$t->column('revoked_at', 'DATETIME DEFAULT NULL');
			$t->column('revoked_by', 'INT(11) DEFAULT NULL');
			$t->primary('ban_id');
			$t->index('idx_bans_ip_pattern', ['ip_pattern']);
			$t->index('idx_bans_wildcard', ['is_wildcard']);
			$t->index('idx_bans_visitor_token_hash', ['visitor_token_hash']);
			$t->index('idx_bans_board_uid', ['board_uid']);
			$t->index('idx_bans_filed_at', ['filed_at']);
			$t->index('idx_bans_expires_at', ['expires_at']);
			$t->index('idx_bans_revoked_at', ['revoked_at']);
			$t->index('idx_bans_post_uid', ['post_uid']);
			$t->foreign('fk_bans_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
			$t->foreign('fk_bans_filed_by', 'filed_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
			$t->foreign('fk_bans_revoked_by', 'revoked_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
			$t->foreign('fk_bans_post_uid', 'post_uid', 'POST_TABLE', 'post_uid', 'SET NULL');
		});

		$schema->createTable('BAN_APPEAL_TABLE', function (tableBlueprint $t): void {
			$t->column('appeal_id', 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT');
			$t->column('ban_id', 'BIGINT(20) UNSIGNED NOT NULL');
			$t->column('appellant_ip', 'VARCHAR(64) NOT NULL');
			$t->column('reason', 'TEXT NOT NULL');
			$t->column('status', 'TINYINT(4) NOT NULL DEFAULT 0');
			$t->column('filed_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('actioned_at', 'DATETIME DEFAULT NULL');
			$t->column('actioned_by', 'INT(11) DEFAULT NULL');
			$t->column('staff_note', 'TEXT DEFAULT NULL');
			$t->primary('appeal_id');
			$t->index('idx_ban_appeals_status_filed', ['status', 'filed_at']);
			$t->index('idx_ban_appeals_ban_id', ['ban_id']);
			$t->index('idx_ban_appeals_actioned_by', ['actioned_by']);
			$t->foreign('fk_ban_appeals_ban_id', 'ban_id', 'BAN_TABLE', 'ban_id', 'CASCADE');
			$t->foreign('fk_ban_appeals_actioned_by', 'actioned_by', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('BAN_APPEAL_TABLE');
		$ctx->schema->dropTable('BAN_TABLE');

		// Retired, so it has no logical key any more; a rollback that recreated it clears it up.
		$ctx->execute('DROP TABLE IF EXISTS `visitor_tokens`');
	}
};
