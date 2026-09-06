<?php

use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Seed the reserved GLOBAL board row.
 *
 * It is the scope every board-wide setting hangs off (the global config row references it), so it
 * has to exist before anything else. The installer used to insert it by hand, which left an
 * install made any other way without one.
 */
return new class extends migration {
	public function description(): string {
		return 'Reserved GLOBAL board row';
	}

	public function up(migrationContext $ctx): void {
		$ctx->execute(
			"INSERT INTO {BOARD_TABLE}
				(board_uid, board_identifier, board_title, board_sub_title, storage_directory_name, listed)
			SELECT ?, 'GLOBAL', 'GLOBAL', 'Global board scope', '', 0 FROM DUAL
			WHERE NOT EXISTS (SELECT 1 FROM {BOARD_TABLE} WHERE board_uid = ?)",
			[GLOBAL_BOARD_UID, GLOBAL_BOARD_UID]
		);
	}

	/** Dropping the row would cascade to every global config override, so this only goes forward. */
	public function down(migrationContext $ctx): void {
		throw new Kokonotsuba\migrations\irreversibleMigrationException(static::class);
	}

	public function detect(migrationContext $ctx): ?bool {
		return (int)$ctx->fetchValue(
			"SELECT COUNT(*) FROM {BOARD_TABLE} WHERE board_uid = ?",
			[GLOBAL_BOARD_UID]
		) > 0;
	}
};
