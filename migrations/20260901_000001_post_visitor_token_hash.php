<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * A short label for the browser each post was made with, recorded on the post itself.
 *
 * visitor_tokens only maps a token to the address it was *last* seen on, which drifts and is
 * shared: reading a post's token back off its address would hand every poster behind one address
 * the same answer, and the wrong one once anybody moved. Writing it at insert time is the only
 * way the association survives.
 *
 * The stored value is the token's fingerprint, not the token: it is enough to tell two posters
 * behind one address apart, and a leaked post table hands nobody a usable token. Rows written
 * before this migration have none, and show none.
 */
return new class extends migration {
	public function description(): string {
		return 'Per-post visitor token fingerprint';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->addColumn('visitor_token_hash', 'VARCHAR(32) DEFAULT NULL', 'host');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->dropColumn('visitor_token_hash');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('POST_TABLE', 'visitor_token_hash');
	}
};
