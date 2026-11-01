<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** Lets "has this browser posted on this board" be an index lookup (lockDown module). */
return new class extends migration {
	public function description(): string {
		return 'Index posts by board and visitor token';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->addIndex('idx_posts_board_token', ['boardUID', 'visitor_token_hash']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->dropIndex('idx_posts_board_token');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->indexExists('POST_TABLE', 'idx_posts_board_token');
	}
};
