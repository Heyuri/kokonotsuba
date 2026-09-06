<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Index a board's posts by uid.
 *
 * Manage-posts and the per-board post lists page through one board's posts newest first. With
 * only the primary key to order by, MariaDB walks post_uid backwards through every other board's
 * posts until it has a page, which on a large multi-board install is most of the table.
 */
return new class extends migration {
	public function description(): string {
		return 'Index posts by board and uid';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->addIndex('idx_posts_board_post_uid', ['boardUID', 'post_uid']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->dropIndex('idx_posts_board_post_uid');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->indexExists('POST_TABLE', 'idx_posts_board_post_uid');
	}
};
