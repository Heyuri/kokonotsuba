<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;
use Kokonotsuba\migrations\tableBlueprint;

/** Front page news entries. The body is staff-written HTML; drafts are staff-only. */
return new class extends migration {
	public function description(): string {
		return 'News entries';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->createTable('NEWS_TABLE', function (tableBlueprint $t): void {
			$t->column('id', 'INT(11) NOT NULL AUTO_INCREMENT');
			$t->column('subject', 'VARCHAR(255) NOT NULL');
			$t->column('body', 'MEDIUMTEXT NOT NULL');
			$t->column('byline', "VARCHAR(255) NOT NULL DEFAULT ''");
			$t->column('email', "VARCHAR(255) NOT NULL DEFAULT ''");
			$t->column('author_id', 'INT(11) DEFAULT NULL');
			$t->column('is_published', 'TINYINT(1) NOT NULL DEFAULT 1');
			$t->column('created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()');
			$t->column('updated_at', 'DATETIME DEFAULT NULL');
			$t->primary('id');
			$t->index('idx_news_published_created', ['is_published', 'created_at', 'id']);
			$t->index('fk_news_author', ['author_id']);
			$t->foreign('fk_news_author', 'author_id', 'ACCOUNT_TABLE', 'id', 'SET NULL');
		});
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->dropTable('NEWS_TABLE');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->tableExists('NEWS_TABLE');
	}
};
