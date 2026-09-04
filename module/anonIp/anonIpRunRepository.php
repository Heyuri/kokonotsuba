<?php

namespace Kokonotsuba\Modules\anonIp;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/**
 * The ledger of anonymization runs.
 *
 * Every timestamp here is written and compared on PHP's clock rather than NOW(), for the same
 * reason the ban system is: PHP and MariaDB may sit in different timezones, and a schedule that
 * reads the wrong clock either never fires or fires on every request.
 */
class anonIpRunRepository extends baseRepository {

	public const TRIGGER_MANUAL = 'manual';
	public const TRIGGER_SCHEDULED = 'scheduled';

	public function __construct(
		databaseConnection $databaseConnection,
		string $runTable,
	) {
		parent::__construct($databaseConnection, $runTable);
	}

	/** Current time in the format the ledger stores. */
	public function now(): string {
		return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
	}

	/**
	 * Claim the scheduled run, if one is due.
	 *
	 * The insert is the claim: it only writes a row when no run has been dispatched since the
	 * cutoff, so of two requests arriving together exactly one gets the row and dispatches. A
	 * run that never finished still counts as having gone, so a job that died does not leave the
	 * schedule dispatching another on every request afterwards.
	 *
	 * @param string   $notBefore     Cutoff: a run dispatched at or after this blocks the claim.
	 * @param int|null $olderThanDays Scope of the run to record, null for everything.
	 * @return int|null The new run's id, or null when a run is not due.
	 */
	public function claimScheduledRun(string $notBefore, ?int $olderThanDays): ?int {
		$inserted = $this->queryAffected(
			"INSERT INTO {$this->table} (older_than_days, trigger_source, dispatched_at)
			 SELECT :older_than_days, :trigger_source, :dispatched_at
			 FROM DUAL
			 WHERE NOT EXISTS (
			     SELECT 1 FROM {$this->table} WHERE dispatched_at >= :not_before
			 )",
			[
				':older_than_days' => $olderThanDays,
				':trigger_source' => self::TRIGGER_SCHEDULED,
				':dispatched_at' => $this->now(),
				':not_before' => $notBefore,
			]
		);

		return $inserted === 1 ? (int) $this->lastInsertId() : null;
	}

	/**
	 * Record a run staff started by hand. Unconditional: the button means now.
	 *
	 * @param int|null $olderThanDays Scope of the run, null for everything.
	 * @return int The new run's id.
	 */
	public function recordManualRun(?int $olderThanDays): int {
		$this->insert([
			'older_than_days' => $olderThanDays,
			'trigger_source' => self::TRIGGER_MANUAL,
			'dispatched_at' => $this->now(),
		]);

		return (int) $this->lastInsertId();
	}

	/**
	 * Close a run out with what it changed.
	 *
	 * @param array<string, int> $breakdown Rows changed per target key.
	 */
	public function markFinished(int $runId, int $rowsChanged, array $breakdown): void {
		$this->updateWhere(
			[
				'finished_at' => $this->now(),
				'rows_changed' => $rowsChanged,
				'breakdown' => json_encode($breakdown),
			],
			'id',
			$runId
		);
	}

	/**
	 * Drop a claimed run whose job never got off the ground, so the next tick tries again
	 * instead of waiting out the whole interval on a dispatch that never happened.
	 */
	public function discardRun(int $runId): void {
		$this->deleteWhere('id', $runId);
	}

	/** The most recent run, or null when the anonymizer has never been run. */
	public function getLastRun(): ?array {
		$row = $this->queryOne(
			"SELECT * FROM {$this->table} ORDER BY dispatched_at DESC, id DESC LIMIT 1"
		);

		return $row === false ? null : $row;
	}
}
