<?php

namespace Kokonotsuba\ban;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/**
 * Storage for appeals filed against bans, and the moderator queue that works through them.
 *
 * Timestamps come from PHP's clock, matching banRepository - see the note there.
 */
class banAppealRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $banAppealTable,
		private readonly string $banTable,
		private readonly string $accountTable,
		private readonly string $boardTable,
	) {
		parent::__construct($databaseConnection, $banAppealTable);
		self::validateTableNames($banTable, $accountTable, $boardTable);
	}

	/** An appeal joined to the ban it argues with, so the queue needs no second query. */
	private function baseSelect(): string {
		return "
			SELECT
				ap.*,
				a.username AS actioned_by_username,
				b.ip_pattern AS ban_ip_pattern,
				b.reason AS ban_reason,
				bo.board_title AS board_title
			FROM {$this->table} ap
			LEFT JOIN {$this->accountTable} a ON a.id = ap.actioned_by
			LEFT JOIN {$this->banTable} b ON b.ban_id = ap.ban_id
			LEFT JOIN {$this->boardTable} bo ON bo.board_uid = b.board_uid";
	}

	public function insertAppeal(int $banId, string $ip, string $reason): int {
		$this->insert([
			'ban_id' => $banId,
			'appellant_ip' => $ip,
			'reason' => $reason,
			'status' => banAppealStatus::PENDING->value,
			'filed_at' => banRepository::now(),
		]);

		return (int) $this->lastInsertId();
	}

	public function findById(int $appealId): ?banAppeal {
		$row = $this->queryOne($this->baseSelect() . ' WHERE ap.appeal_id = ?', [$appealId]);

		return $row === false ? null : banAppeal::fromRow($row);
	}

	/** @return list<banAppeal> Newest first, so a ban's own page can show its appeal history. */
	public function findForBan(int $banId): array {
		return $this->hydrateAll($this->queryAll(
			$this->baseSelect() . ' WHERE ap.ban_id = ? ORDER BY ap.filed_at DESC',
			[$banId]
		));
	}

	public function findPendingForBan(int $banId): ?banAppeal {
		$row = $this->queryOne(
			$this->baseSelect() . ' WHERE ap.ban_id = ? AND ap.status = ? ORDER BY ap.filed_at DESC',
			[$banId, banAppealStatus::PENDING->value]
		);

		return $row === false ? null : banAppeal::fromRow($row);
	}

	/** When this ban was last denied an appeal, which is what the re-appeal cooldown runs from. */
	public function getLastDeniedAt(int $banId): ?int {
		$value = $this->queryValue(
			"SELECT MAX(actioned_at) FROM {$this->table} WHERE ban_id = ? AND status = ?",
			[$banId, banAppealStatus::DENIED->value]
		);

		if ($value === null || $value === false || $value === '') {
			return null;
		}

		$timestamp = strtotime((string) $value);

		return $timestamp === false ? null : $timestamp;
	}

	/**
	 * @param string $status 'pending', 'actioned' or 'all'.
	 * @return list<banAppeal>
	 */
	public function listAppeals(string $status, int $limit, int $offset): array {
		[$where, $params] = $this->buildStatusFilter($status);

		// Cast-interpolated for the same reason as banRepository::listBans().
		$query = $this->baseSelect() . $where
			. ' ORDER BY ap.status ASC, ap.filed_at DESC'
			. ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

		return $this->hydrateAll($this->queryAll($query, $params));
	}

	public function countAppeals(string $status): int {
		[$where, $params] = $this->buildStatusFilter($status);

		return (int) $this->queryValue("SELECT COUNT(*) FROM {$this->table} ap" . $where, $params);
	}

	public function countPending(): int {
		return $this->countAppeals('pending');
	}

	/**
	 * Drop the pending appeals of bans that have run out: there is nothing left to lift.
	 *
	 * @return int Rows removed.
	 */
	public function deletePendingForLapsedBans(): int {
		return $this->queryAffected(
			"DELETE ap FROM {$this->table} ap
			INNER JOIN {$this->banTable} b ON b.ban_id = ap.ban_id
			WHERE ap.status = ? AND b.expires_at IS NOT NULL AND b.expires_at <= ?",
			[banAppealStatus::PENDING->value, banRepository::now()]
		);
	}

	/** @return array{0: string, 1: array} */
	private function buildStatusFilter(string $status): array {
		return match ($status) {
			'pending' => [' WHERE ap.status = ?', [banAppealStatus::PENDING->value]],
			'actioned' => [' WHERE ap.status <> ?', [banAppealStatus::PENDING->value]],
			default => ['', []],
		};
	}

	/**
	 * Close the given appeals, skipping any a colleague already handled.
	 *
	 * @param list<int> $appealIds
	 * @return int Rows actually closed.
	 */
	public function decideAppeals(array $appealIds, banAppealStatus $decision, ?int $accountId, string $staffNote): int {
		if ($appealIds === []) {
			return 0;
		}

		$params = array_merge(
			[$decision->value, banRepository::now(), $accountId, $staffNote],
			$appealIds,
			[banAppealStatus::PENDING->value]
		);

		// The status guard is the whole point of this being a count: appeals a colleague already
		// handled do not match, so the affected-row count is what was actually closed. Returning
		// count($appealIds) claimed every id had been.
		return $this->queryAffected(
			"UPDATE {$this->table}
			SET status = ?, actioned_at = ?, actioned_by = ?, staff_note = ?
			WHERE appeal_id IN " . pdoPlaceholdersForIn($appealIds) . ' AND status = ?',
			$params
		);
	}

	/** @return list<banAppeal> */
	private function hydrateAll(array $rows): array {
		return array_map(fn(array $row): banAppeal => banAppeal::fromRow($row), $rows);
	}
}
