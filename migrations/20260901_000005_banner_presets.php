<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Which kind of banner a row is.
 *
 * banner_ads held one kind, the full banner ad, so every row that predates this is that and the
 * column default says so. Board banners were loose files under the static image/banner directory
 * and are carried across by Utilities/banner-import-cli.php.
 */
return new class extends migration {
	public function description(): string {
		return 'Banner presets on banner_ads';
	}

	/** DDL, so MariaDB commits implicitly: not a rollback unit. */
	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('BANNER_AD_TABLE')
			->addColumn('preset', "VARCHAR(32) NOT NULL DEFAULT 'ad'", 'banner_file_name')
			->addIndex('idx_preset_active_approved', ['preset', 'is_active', 'is_approved']);
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('BANNER_AD_TABLE')
			->dropIndex('idx_preset_active_approved')
			->dropColumn('preset');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('BANNER_AD_TABLE', 'preset');
	}
};
