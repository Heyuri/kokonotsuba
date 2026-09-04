<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Split a ban's reasons three ways, and let a ban refuse appeals.
 *
 * `reason` is what the banned party reads. `public_reason` is the notice shown under the post
 * they were banned for - it used to be baked into the post's comment at ban time, which made it
 * uneditable afterwards, so it lives on the ban now and is rendered from there. `private_reason`
 * is staff-only and never leaves the admin pages.
 */
return new class extends migration {
	public function description(): string {
		return 'Public and private ban reasons, and the no-appeals flag';
	}

	public function isTransactional(): bool {
		return false;
	}

	public function up(migrationContext $ctx): void {
		$ctx->schema->table('BAN_TABLE')
			->addColumn('public_reason', 'TEXT DEFAULT NULL', 'reason')
			->addColumn('private_reason', 'TEXT DEFAULT NULL', 'public_reason')
			->addColumn('rejects_appeals', 'TINYINT(1) NOT NULL DEFAULT 0', 'is_mute');
	}

	public function down(migrationContext $ctx): void {
		$ctx->schema->table('BAN_TABLE')
			->dropColumn('rejects_appeals')
			->dropColumn('private_reason')
			->dropColumn('public_reason');
	}

	public function detect(migrationContext $ctx): ?bool {
		return $ctx->schema->columnExists('BAN_TABLE', 'public_reason');
	}
};
