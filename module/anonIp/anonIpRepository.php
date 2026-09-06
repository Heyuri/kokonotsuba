<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpTarget.php';

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\ip\ipAnonymizer;

/**
 * Applies anonymization to one anonIpTarget at a time.
 *
 * A row counts as already done when a hashed column holds exactly ipAnonymizer::HASH_LENGTH
 * lowercase hex characters, or when a cleared column is empty, so every operation is idempotent
 * and a re-run costs only the scan.
 *
 * The primary table is the posts table because that is the schema's main IP store, but no
 * operation here uses it: each one names its own table through the target it is given.
 */
class anonIpRepository extends baseRepository {

	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable,
		private readonly ipAnonymizer $anonymizer,
	) {
		parent::__construct($databaseConnection, $postTable);
	}

	/**
	 * Rows in the target still holding an address, optionally limited to those older than
	 * the cutoff.
	 *
	 * @param string|null $cutoff MySQL datetime (Y-m-d H:i:s), or null for every row.
	 */
	public function countPending(anonIpTarget $target, ?string $cutoff): int {
		[$where, $params] = $this->buildWhere($target, $cutoff);

		return (int) $this->queryColumn(
			"SELECT COUNT(*) FROM `{$target->table}` WHERE {$where}",
			$params
		);
	}

	/**
	 * Anonymize the target, optionally limited to rows older than the cutoff.
	 *
	 * @param string|null $cutoff MySQL datetime (Y-m-d H:i:s), or null for every row.
	 * @return int Rows changed.
	 */
	public function anonymize(anonIpTarget $target, ?string $cutoff): int {
		[$where, $params] = $this->buildWhere($target, $cutoff);

		if ($target->mode === anonIpTarget::MODE_CLEAR) {
			$set = "`{$target->ipColumn}` = :clear_to";
			$params[':clear_to'] = $target->clearTo;
		} else {
			$set = "`{$target->ipColumn}` = " . ipAnonymizer::hashColumnSql("`{$target->ipColumn}`");
			$params[':anon_salt'] = $this->anonymizer->requireSalt();
		}

		return $this->queryAffected(
			"UPDATE `{$target->table}` SET {$set} WHERE {$where}",
			$params
		);
	}

	/**
	 * The WHERE shared by the count and the update: not already done, within the target's own
	 * guard, and older than the cutoff when one was given.
	 *
	 * @return array{0: string, 1: array} SQL fragment and its bound parameters.
	 */
	private function buildWhere(anonIpTarget $target, ?string $cutoff): array {
		$column = "`{$target->ipColumn}`";

		$clauses = [$this->pendingSql($target, $column)];
		$params = [];

		if ($target->mode === anonIpTarget::MODE_CLEAR && $target->clearTo !== null) {
			$params[':clear_pending'] = $target->clearTo;
		}

		if ($target->guardSql !== '') {
			$clauses[] = "({$target->guardSql})";
			$params += $target->guardParams;
		}

		if ($cutoff !== null) {
			if (!$target->canBeAged()) {
				throw new \LogicException("Target '{$target->key}' cannot be filtered by age.");
			}
			$clauses[] = "({$target->cutoffSql})";
			$params[':cutoff'] = $cutoff;
		}

		return [implode(' AND ', $clauses), $params];
	}

	/** Rows this target has not dealt with yet. */
	private function pendingSql(anonIpTarget $target, string $column): string {
		if ($target->mode !== anonIpTarget::MODE_CLEAR) {
			return ipAnonymizer::notAnonymizedSql($column);
		}

		// A clear is done when the column already holds the value it writes. NULL never compares
		// equal, so each cleared-to value needs its own test.
		return $target->clearTo === null
			? "{$column} IS NOT NULL"
			: "({$column} IS NULL OR {$column} <> :clear_pending)";
	}
}
