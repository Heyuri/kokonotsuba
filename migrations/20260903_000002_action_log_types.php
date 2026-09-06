<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Structured event type on the action log.
 *
 * The log used to be filtered by matching substrings of its prose, which caught "Uploaded banner"
 * under bans and "Deleted blotter entry" under deletions. Entries now carry the type they were
 * logged with. Rows written before this migration are backfilled from their text, most specific
 * pattern first; anything unrecognised stays "other".
 */
return new class extends migration {
	public function description(): string {
		return 'Action log event types';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	/** Prose pattern => action type, applied in order, each only to still-untyped rows. */
	private const BACKFILL = [
		'Restored attachment %' => 'post.restore',
		'Restored post No.%' => 'post.edit',
		'Restored%' => 'post.restore',
		'Purged%' => 'post.purge',
		'Deleted file for post%' => 'post.file_delete',
		'% (file only)' => 'post.file_delete',
		'Deleted post%' => 'post.delete',
		'Delete posts:%' => 'post.delete',
		'Edited own post No.%' => 'post.edit',
		'Edited post No.%' => 'post.edit',
		'Moved thread%' => 'post.move',
		'Merged%' => 'post.move',
		'Post No.% registered' => 'post.register',

		'Warned %' => 'ban.issue',
		'Banned and deleted file hash:%' => 'tool.file_ban',
		'Banned file hash:%' => 'tool.file_ban',
		'Perceptually banned%' => 'tool.file_ban',
		'Banned%' => 'ban.issue',
		'Permanently banned%' => 'ban.issue',
		'Muted %' => 'ban.issue',
		'Edited the ban on%' => 'ban.edit',
		'Revoked the ban on%' => 'ban.revoke',
		'Approved % ban appeal%' => 'ban.appeal',
		'Denied % ban appeal%' => 'ban.appeal',

		'Logged in' => 'account.login',
		'Failed attempted log-in%' => 'account.login_failed',
		'Registered a new account%' => 'account.create',
		'Reset password' => 'account.password',
		'Admin reset password%' => 'account.password',

		'Rebuilt%' => 'board.rebuild',
		'Queued rebuild%' => 'board.rebuild',

		'Uploaded % banner%' => 'content.banner',
		'% banner(s)' => 'content.banner',
		'% blotter entr%' => 'content.blotter',
		'% ad for slot%' => 'content.ad',
		'% ad(s):%' => 'content.ad',

		'% capcode (%' => 'tool.capcode',
		'Queued IP anonymization%' => 'tool.anon_ip',
		'% private message(s)' => 'tool.pm',
	];

	public function up(migrationContext $ctx): void {
		if (!$ctx->schema->columnExists('ACTIONLOG_TABLE', 'action_type')) {
			$ctx->schema->table('ACTIONLOG_TABLE')
				->addColumn('action_type', "VARCHAR(64) NOT NULL DEFAULT 'other'", 'log_action');
		}

		if (!$ctx->schema->indexExists('ACTIONLOG_TABLE', 'idx_actionlog_action_type')) {
			$ctx->schema->table('ACTIONLOG_TABLE')->addIndex('idx_actionlog_action_type', ['action_type']);
		}

		foreach (self::BACKFILL as $pattern => $type) {
			$ctx->execute(
				"UPDATE {ACTIONLOG_TABLE} SET action_type = ? WHERE action_type = 'other' AND log_action LIKE ?",
				[$type, $pattern]
			);
		}
	}

	public function down(migrationContext $ctx): void {
		if ($ctx->schema->columnExists('ACTIONLOG_TABLE', 'action_type')) {
			$ctx->schema->table('ACTIONLOG_TABLE')->dropColumn('action_type');
		}
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('ACTIONLOG_TABLE', 'action_type');
	}
};
