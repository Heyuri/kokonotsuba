<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** Lets the thread watcher find a thread's first unread post by walking back from its newest. */
return new class extends migration {
	public function description(): string {
		return 'Index posts by thread and post number';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->addIndex('idx_posts_thread_no', ['thread_uid', 'no']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->dropIndex('idx_posts_thread_no');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->indexExists('POST_TABLE', 'idx_posts_thread_no');
	}
};
