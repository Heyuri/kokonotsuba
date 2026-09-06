<?php

use Kokonotsuba\account\legacyRoleLevelMap;
use Kokonotsuba\migrations\migration;
use Kokonotsuba\migrations\migrationContext;

/**
 * Rewrite stored role levels from the old contiguous numbering to the spaced one in userRole.
 *
 * Values that are neither legacy nor current are left alone: nothing can be said about what they
 * were meant to mean, and the CASE only touches the values it recognises.
 *
 * Staff sessions still hold the old integer afterwards and resolve to None until the next login.
 * Clearing the PHP session store avoids the confusion.
 */
return new class extends migration {
	private const TABLE_KEYS = ['ACCOUNT_TABLE', 'ACTIONLOG_TABLE'];

	public function description(): string {
		return 'Role levels: old contiguous numbering -> current userRole values';
	}

	public function up(migrationContext $ctx): void {
		$legacyValues = array_keys(legacyRoleLevelMap::changingMap());
		if ($legacyValues === []) {
			return;
		}

		$placeholders = implode(', ', array_fill(0, count($legacyValues), '?'));
		$caseExpression = legacyRoleLevelMap::caseExpression('role');

		foreach (self::TABLE_KEYS as $tableKey) {
			$ctx->execute(
				"UPDATE {{$tableKey}} SET `role` = {$caseExpression} WHERE `role` IN ({$placeholders})",
				array_values($legacyValues)
			);
		}
	}

	/** Only 0 overlaps between the two numberings and means None in both, so this cannot be undone. */
	public function down(migrationContext $ctx): void {
		throw new Kokonotsuba\migrations\irreversibleMigrationException(static::class);
	}

	public function detect(migrationContext $ctx): ?bool {
		$legacyValues = array_keys(legacyRoleLevelMap::changingMap());
		if ($legacyValues === []) {
			return true;
		}

		$placeholders = implode(', ', array_fill(0, count($legacyValues), '?'));

		foreach (self::TABLE_KEYS as $tableKey) {
			$remaining = (int)$ctx->fetchValue(
				"SELECT COUNT(*) FROM {{$tableKey}} WHERE `role` IN ({$placeholders})",
				array_values($legacyValues)
			);

			if ($remaining > 0) {
				return false;
			}
		}

		return true;
	}
};
