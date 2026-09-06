<?php

namespace Kokonotsuba\Modules\anonIp;

use Kokonotsuba\database\ValidatesIdentifiersTrait;

/**
 * One IP-bearing column the anonymizer knows about.
 *
 * A target is either hashed, replacing the address with its salted hash so the row stays
 * matchable against the same address, or cleared, discarding a value that identifies a visitor
 * but has nothing to match against. A cleared target names the value it writes: the empty string
 * for a NOT NULL column that already means "nothing" that way, NULL for a nullable one.
 */
final class anonIpTarget {
	use ValidatesIdentifiersTrait;

	public const MODE_HASH = 'hash';
	public const MODE_CLEAR = 'clear';

	/**
	 * @param string      $key         Stable identifier, used in the per-table breakdown.
	 * @param string      $table       Real table name.
	 * @param string      $ipColumn    Column holding the address.
	 * @param string      $mode        MODE_HASH or MODE_CLEAR.
	 * @param string|null $cutoffSql   WHERE fragment selecting rows older than the bound
	 *                                 :cutoff, or null when the rows cannot be aged and only a
	 *                                 full run touches them.
	 * @param string      $guardSql    Extra WHERE fragment restricting which rows may be
	 *                                 touched at all, ANDed into both run modes.
	 * @param array       $guardParams Bound parameters for $guardSql.
	 * @param string|null $clearTo     Value a MODE_CLEAR target writes, and the one it treats as
	 *                                 already done. Ignored by MODE_HASH.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $table,
		public readonly string $ipColumn,
		public readonly string $mode = self::MODE_HASH,
		public readonly ?string $cutoffSql = null,
		public readonly string $guardSql = '',
		public readonly array $guardParams = [],
		public readonly ?string $clearTo = '',
	) {
		self::validateTableName($table);

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $ipColumn)) {
			throw new \InvalidArgumentException("Invalid column name: {$ipColumn}");
		}

		if ($mode !== self::MODE_HASH && $mode !== self::MODE_CLEAR) {
			throw new \InvalidArgumentException("Unknown anonymization mode: {$mode}");
		}
	}

	/** Whether a cutoff run can select rows from this table by age. */
	public function canBeAged(): bool {
		return $this->cutoffSql !== null;
	}
}
