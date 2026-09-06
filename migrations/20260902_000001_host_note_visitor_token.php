<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Let a staff note be filed against a browser fingerprint instead of a host.
 *
 * A note now names exactly one target: ip_pattern for a host or a range, visitor_token_hash for
 * a browser. ip_pattern therefore becomes nullable, since a browser note has no address to hold,
 * and the hash is indexed because looking notes up by it happens once per post rendered.
 */
return new class extends migration {
	public function description(): string {
		return 'Host notes may target a visitor token fingerprint';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('HOST_NOTE_TABLE')
			->addColumn('visitor_token_hash', 'VARCHAR(32) DEFAULT NULL', 'ip_pattern')
			->modifyColumn('ip_pattern', 'VARCHAR(255) DEFAULT NULL')
			->addIndex('idx_host_notes_visitor_token_hash', ['visitor_token_hash']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->execute('DELETE FROM {HOST_NOTE_TABLE} WHERE visitor_token_hash IS NOT NULL');

		$ctx->schema->table('HOST_NOTE_TABLE')
			->dropIndex('idx_host_notes_visitor_token_hash')
			->dropColumn('visitor_token_hash')
			->modifyColumn('ip_pattern', 'VARCHAR(255) NOT NULL');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('HOST_NOTE_TABLE', 'visitor_token_hash');
	}
};
