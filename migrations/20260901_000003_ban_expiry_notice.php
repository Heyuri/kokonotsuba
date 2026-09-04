<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * When the banned party was told their ban had run out.
 *
 * A lapsed ban is not lifted the moment the clock passes it: the old flat-file system held the
 * ban until it had been read one last time, so that whoever it stopped is told they are free
 * rather than simply finding that posting works again. This column is what remembers that
 * telling; until it is set, a lapsed ban still interrupts the checkpoint it blocked, once.
 *
 * Bans that had already lapsed before this existed are stamped as told, so nothing anybody
 * served years ago comes back to interrupt them.
 */
return new class extends migration {
	public function description(): string {
		return 'Expiry notice on bans';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('BAN_TABLE')
			->addColumn('expiry_seen_at', 'DATETIME DEFAULT NULL', 'seen_cookies');

		$ctx->execute(
			'UPDATE {BAN_TABLE} SET expiry_seen_at = expires_at
			WHERE expiry_seen_at IS NULL AND expires_at IS NOT NULL AND expires_at <= ?',
			[date('Y-m-d H:i:s')]
		);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('BAN_TABLE')
			->dropColumn('expiry_seen_at');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('BAN_TABLE', 'expiry_seen_at');
	}
};
