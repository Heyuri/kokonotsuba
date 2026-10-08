<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\tableBlueprint;
use Kokonotsuba\migrations\migrationContext;

/**
 * How often each cached thread fragment is served from the cache, and how often it had to be drawn.
 *
 * One row per board, thread and variant, added to once per request by the threadCache module.
 * Counts are all-time: a row outlives its thread, and only goes with its board.
 */
return new class extends migration {
	public function description(): string {
		return 'Thread fragment cache hit counters';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('THREAD_FRAGMENT_HIT_TABLE', function (tableBlueprint $t): void {
			$t->column('board_uid', 'INT(11) NOT NULL');
			$t->column('thread_uid', 'VARCHAR(255) NOT NULL');
			// threadFragmentCache's variant: index-{n}, thread-{page} or overboard-{template}-{n}
			$t->column('variant', 'VARCHAR(96) NOT NULL');
			$t->column('hits', 'BIGINT(20) UNSIGNED NOT NULL DEFAULT 0');
			$t->column('misses', 'BIGINT(20) UNSIGNED NOT NULL DEFAULT 0');
			$t->column('last_hit_at', 'DATETIME DEFAULT NULL');
			$t->primary('board_uid', 'thread_uid', 'variant');
			$t->foreign('fk_thread_fragment_hits_board_uid', 'board_uid', 'BOARD_TABLE', 'board_uid', 'CASCADE');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('THREAD_FRAGMENT_HIT_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('THREAD_FRAGMENT_HIT_TABLE');
	}
};
