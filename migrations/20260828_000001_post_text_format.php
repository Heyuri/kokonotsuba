<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Record how each post's text columns are stored.
 *
 * Posts used to be escaped and marked up on the way into the database, so `com` held <br> tags,
 * autolink anchors and whatever markup modules baked in. Posts are stored as typed now and
 * formatted at render time. Existing rows keep the default 0 (legacy HTML) and are still emitted
 * verbatim; Utilities/post-text-format-cli.php converts them and flips the flag.
 *
 * @see Kokonotsuba\post\textFormat
 */
return new class extends migration {
	public function description(): string {
		return 'Per-post text storage format (legacy HTML / plain text / raw HTML)';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->addColumn('text_format', 'TINYINT(1) NOT NULL DEFAULT 0', 'status');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('POST_TABLE')
			->dropColumn('text_format');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('POST_TABLE', 'text_format');
	}
};
