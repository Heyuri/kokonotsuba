<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Index the per-post browser fingerprint.
 *
 * Staff can now click a post's browser label to gather every post made with it, which filters on
 * an equality across every board at once. Without an index that is a scan of the whole post
 * table per click.
 */
return new class extends migration {
	public function description(): string {
		return 'Index posts by visitor token fingerprint';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->addIndex('idx_visitor_token_hash', ['visitor_token_hash']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->dropIndex('idx_visitor_token_hash');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->indexExists('POST_TABLE', 'idx_visitor_token_hash');
	}
};
