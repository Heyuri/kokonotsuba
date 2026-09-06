<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Retire the visitor_tokens table and tie bans to a fingerprint instead of a token.
 *
 * The table mapped a token to the address it was *last* seen on, overwritten on every move. It
 * played no part in enforcement - a ban tied to a browser is matched against the signed cookie
 * the visitor is carrying, which vouches for itself - and served only two readers: honouring
 * tokens minted before signing existed, and guessing which browser to tie a ban to from an
 * address, which on a shared address guesses wrong. Posts now carry their own fingerprint, which
 * is the same association recorded per event and never overwritten, so the guess has a real
 * answer to defer to and the table has no reader left.
 *
 * Bans keep the tie, as the fingerprint rather than the token: matching recomputes it from the
 * cookie on each request, so no token is stored anywhere on the server. Ties already filed hold
 * raw token ids, which cannot be converted without the install secret, and are cleared - those
 * bans still enforce on their address.
 */
return new class extends migration {
	public function description(): string {
		return 'Retire visitor_tokens; bans tie to a token fingerprint';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		if ($ctx->schema->columnExists('BAN_TABLE', 'visitor_token')) {
			$ctx->schema->table('BAN_TABLE')
				->dropIndex('idx_bans_visitor_token')
				->renameColumn('visitor_token', 'visitor_token_hash', 'VARCHAR(32) DEFAULT NULL')
				->addIndex('idx_bans_visitor_token_hash', ['visitor_token_hash']);

			// Raw token ids: not fingerprints, and not convertible into them here.
			$ctx->execute('UPDATE {BAN_TABLE} SET visitor_token_hash = NULL');
		}

		// The logical key is gone from tables.php by design, so the physical name is the only
		// handle left. Fresh installs never create it and drop nothing.
		$ctx->execute('DROP TABLE IF EXISTS `visitor_tokens`');
	}

	/**
	 * Puts the schema back, not the data: the table returns empty, which is all the reverse of a
	 * drop can offer. Nothing read it, so nothing notices.
	 */
	public function down(migrationContext $ctx): void {
		if ($ctx->schema->columnExists('BAN_TABLE', 'visitor_token_hash')) {
			$ctx->schema->table('BAN_TABLE')
				->dropIndex('idx_bans_visitor_token_hash')
				->renameColumn('visitor_token_hash', 'visitor_token', 'VARCHAR(64) DEFAULT NULL')
				->addIndex('idx_bans_visitor_token', ['visitor_token']);
		}

		$ctx->execute(
			'CREATE TABLE IF NOT EXISTS `visitor_tokens` (
				`token` VARCHAR(64) NOT NULL,
				`ip_address` VARCHAR(64) NOT NULL,
				`first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				`last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				PRIMARY KEY (`token`),
				INDEX `idx_visitor_tokens_ip` (`ip_address`),
				INDEX `idx_visitor_tokens_last_seen` (`last_seen`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
		);
	}

	/** Detected by the column the rename leaves behind. */
	public function detect(migrationContext $ctx): ?bool {
		return !$ctx->schema->columnExists('BAN_TABLE', 'visitor_token')
			&& $ctx->schema->columnExists('BAN_TABLE', 'visitor_token_hash');
	}
};
