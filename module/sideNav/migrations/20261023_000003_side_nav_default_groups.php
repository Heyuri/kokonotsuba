<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** The default "Bulletin boards" group, listing the listed boards. */
return new class extends migration {
	public function description(): string {
		return 'Side nav default groups';
	}

	public function up(migrationContext $ctx): void {
		// A dry run only printed the CREATE TABLE before this, so the table may not exist yet.
		if ($ctx->schema->tableExists('SIDE_NAV_GROUP_TABLE')
			&& (int) $ctx->fetchValue('SELECT COUNT(*) FROM {SIDE_NAV_GROUP_TABLE}') > 0) {
			return;
		}

		$ctx->execute(
			"INSERT INTO {SIDE_NAV_GROUP_TABLE} (title, kind, sort_order) VALUES (:title, 'boards', 0)",
			[':title' => 'Bulletin boards']
		);
	}

	/** The seeded group is an ordinary row now. */
	public function down(migrationContext $ctx): void {
	}

	public function detect(migrationContext $ctx): ?bool {
		if (!$ctx->schema->tableExists('SIDE_NAV_GROUP_TABLE')) {
			return false;
		}

		return (int) $ctx->fetchValue('SELECT COUNT(*) FROM {SIDE_NAV_GROUP_TABLE}') > 0;
	}
};
