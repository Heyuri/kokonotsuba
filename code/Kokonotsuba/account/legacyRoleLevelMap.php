<?php

namespace Kokonotsuba\account;

use Kokonotsuba\userRole;

/**
 * Translation from the old contiguous role numbering (None 0 ... System 5) to the current spaced
 * values in userRole. Drives Utilities/migrateRoleLevels-cli.php, which rewrites the `role` column
 * of the account and action log tables.
 *
 * Only 0 appears in both numberings, and it means None in both, so applying the map twice is a
 * no-op - the migration is safe to re-run.
 */
final class legacyRoleLevelMap {

	/** Legacy role integer => the role it means today. */
	private const LEGACY_TO_ROLE = [
		0 => userRole::LEV_NONE,
		1 => userRole::LEV_USER,
		2 => userRole::LEV_JANITOR,
		3 => userRole::LEV_MODERATOR,
		4 => userRole::LEV_ADMIN,
		5 => userRole::LEV_SYSTEM,
	];

	/**
	 * The full legacy => current integer map.
	 *
	 * @return array<int,int>
	 */
	public static function map(): array {
		return array_map(fn(userRole $role): int => $role->value, self::LEGACY_TO_ROLE);
	}

	/**
	 * The legacy => current map with the entries that would not change anything removed.
	 *
	 * @return array<int,int>
	 */
	public static function changingMap(): array {
		return array_filter(self::map(), fn(int $new, int $legacy): bool => $new !== $legacy, ARRAY_FILTER_USE_BOTH);
	}

	/** The current value for a legacy role integer, or null if it was never a legacy role. */
	public static function newValueFor(int $legacyValue): ?int {
		return self::map()[$legacyValue] ?? null;
	}

	/** Whether a stored value is already a current role value. */
	public static function isCurrentValue(int $value): bool {
		return userRole::tryFrom($value) !== null;
	}

	/**
	 * Whether a stored value still needs rewriting: it is a legacy value, and rewriting it would
	 * change it. 0 is a legacy value but means None either way, so it is left alone.
	 */
	public static function needsMigration(int $value): bool {
		return array_key_exists($value, self::changingMap());
	}

	/**
	 * Sort a set of stored role values into what the migration will do with them.
	 *
	 * `unknown` is the dangerous bucket: values that are neither a legacy role nor a current one,
	 * so nothing can be said about what they were meant to mean.
	 *
	 * @param int[] $values
	 * @return array{migrate: int[], current: int[], unknown: int[]}
	 */
	public static function classify(array $values): array {
		$result = ['migrate' => [], 'current' => [], 'unknown' => []];

		foreach (array_values(array_unique($values)) as $value) {
			if (self::needsMigration($value)) {
				$result['migrate'][] = $value;
			} elseif (self::isCurrentValue($value)) {
				$result['current'][] = $value;
			} else {
				$result['unknown'][] = $value;
			}
		}

		return $result;
	}

	/**
	 * A SQL CASE expression rewriting $column from legacy values to current ones, leaving anything
	 * else untouched. Values are inlined rather than bound because they are class constants - the
	 * only caller-supplied part is the column name, which is validated.
	 */
	public static function caseExpression(string $column): string {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
			throw new \InvalidArgumentException("Invalid column name: $column");
		}

		$whens = '';
		foreach (self::changingMap() as $legacy => $new) {
			$whens .= " WHEN {$legacy} THEN {$new}";
		}

		return "CASE `{$column}`{$whens} ELSE `{$column}` END";
	}
}
