<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/** The default "Main" group (home, overboard, hide nav), placed above every other group. */
return new class extends migration {
	public function description(): string {
		return 'Side nav main group';
	}

	public function up(migrationContext $ctx): void {
		// A dry run only printed the CREATE TABLE before this, so the table may not exist yet.
		if ($ctx->schema->tableExists('SIDE_NAV_GROUP_TABLE')
			&& (int) $ctx->fetchValue("SELECT COUNT(*) FROM {SIDE_NAV_GROUP_TABLE} WHERE kind = 'main'") > 0) {
			return;
		}

		$first = $ctx->schema->tableExists('SIDE_NAV_GROUP_TABLE')
			? (int) $ctx->fetchValue('SELECT COALESCE(MIN(sort_order), 0) FROM {SIDE_NAV_GROUP_TABLE}')
			: 0;
		$ctx->execute(
			"INSERT INTO {SIDE_NAV_GROUP_TABLE} (title, kind, sort_order) VALUES (:title, 'main', :sort_order)",
			[':title' => 'Main', ':sort_order' => $first - 1]
		);
	}

	/** The seeded group is an ordinary row now. */
	public function down(migrationContext $ctx): void {
	}

	public function detect(migrationContext $ctx): ?bool {
		if (!$ctx->schema->tableExists('SIDE_NAV_GROUP_TABLE')) {
			return false;
		}

		return (int) $ctx->fetchValue("SELECT COUNT(*) FROM {SIDE_NAV_GROUP_TABLE} WHERE kind = 'main'") > 0;
	}
};
