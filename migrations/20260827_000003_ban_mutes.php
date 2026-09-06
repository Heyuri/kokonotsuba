<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Mark the short automatic bans - janitor mutes and spam-filter mutes - as mutes.
 *
 * They are filed in bursts during a flood and are worthless once they lapse, so they are pruned
 * rather than kept. Flagging them is what lets the ban table tell them apart from a ban a
 * moderator actually sat down and wrote.
 */
return new class extends migration {
	public function description(): string {
		return 'Mute flag on bans';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('BAN_TABLE')
			->addColumn('is_mute', 'TINYINT(1) NOT NULL DEFAULT 0', 'is_warning')
			->addIndex('idx_bans_mute_expiry', ['is_mute', 'expires_at']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('BAN_TABLE')
			->dropIndex('idx_bans_mute_expiry')
			->dropColumn('is_mute');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('BAN_TABLE', 'is_mute');
	}
};
