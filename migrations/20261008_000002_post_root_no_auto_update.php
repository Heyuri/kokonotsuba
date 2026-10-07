<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Stop `root` moving whenever its post is written.
 *
 * Redeclared as the baseline has it. The connection turns on explicit_defaults_for_timestamp,
 * so this no longer picks up ON UPDATE CURRENT_TIMESTAMP on older servers.
 */
return new class extends migration {
	public function description(): string {
		return 'Drop ON UPDATE from posts.root';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		if (!$this->detect($ctx)) {
			$ctx->schema->table('POST_TABLE')->modifyColumn('root', 'TIMESTAMP NOT NULL');
		}
	}

	/** Putting the auto-update back would only damage post times again. */
	public function down(migrationContext $ctx): void {}

	public function detect(migrationContext $ctx): ?bool {
		$posts = $ctx->table('POST_TABLE');
		if (!$ctx->inspector->tableExists($posts)) {
			return true;
		}

		$column = $ctx->inspector->getColumns($posts)['root'] ?? null;

		return $column === null || stripos($column['extra'], 'on update') === false;
	}
};
