<?php

namespace Kokonotsuba\Modules\threadCache;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/**
 * All-time hit and miss counts of the thread fragment cache, per board, thread and variant.
 *
 * Timestamps are written on PHP's clock, like the rest of the staff-facing ledgers.
 */
class threadCacheHitRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $hitTable,
		private readonly string $threadTable,
	) {
		parent::__construct($databaseConnection, $hitTable);
		self::validateTableName($threadTable);
	}

	/**
	 * Add one request's counts in a single statement.
	 *
	 * @param list<array{boardUid: int, threadUid: string, variant: string, hits: int, misses: int}> $rows
	 *        Sorted by key, so two requests touching the same rows lock them in the same order.
	 */
	public function record(array $rows): void {
		if ($rows === []) {
			return;
		}

		$now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
		$values = [];
		$params = [];
		foreach ($rows as $i => $row) {
			$values[] = "(:b{$i}, :t{$i}, :v{$i}, :h{$i}, :m{$i}, :l{$i})";
			$params[":b{$i}"] = $row['boardUid'];
			$params[":t{$i}"] = $row['threadUid'];
			$params[":v{$i}"] = $row['variant'];
			$params[":h{$i}"] = $row['hits'];
			$params[":m{$i}"] = $row['misses'];
			$params[":l{$i}"] = $row['hits'] > 0 ? $now : null;
		}

		$this->query(
			"INSERT INTO {$this->table} (board_uid, thread_uid, variant, hits, misses, last_hit_at)
			 VALUES " . implode(', ', $values) . "
			 ON DUPLICATE KEY UPDATE
			     hits = hits + VALUES(hits),
			     misses = misses + VALUES(misses),
			     last_hit_at = COALESCE(VALUES(last_hit_at), last_hit_at)",
			$params
		);
	}

	/**
	 * Totals per board and kind of variant (index, thread, overboard).
	 *
	 * @return list<array{board_uid: int, kind: string, hits: int, misses: int, last_hit_at: ?string}>
	 */
	public function totalsByBoardAndKind(): array {
		return $this->queryAll(
			"SELECT board_uid, SUBSTRING_INDEX(variant, '-', 1) AS kind,
			        SUM(hits) AS hits, SUM(misses) AS misses, MAX(last_hit_at) AS last_hit_at
			 FROM {$this->table}
			 GROUP BY board_uid, kind"
		);
	}

	/**
	 * The threads served from the cache most, every variant summed. Threads no longer in the
	 * thread table are left out; their counts still go into the totals.
	 *
	 * @return list<array{board_uid: int, thread_uid: string, post_op_number: int, hits: int, misses: int, last_hit_at: ?string}>
	 */
	public function mostHitThreads(int $limit, ?int $boardUid = null): array {
		$params = [];
		$boardFilter = '';
		if ($boardUid !== null) {
			$boardFilter = 'WHERE board_uid = :board_uid';
			$params[':board_uid'] = $boardUid;
		}

		$query = "SELECT a.board_uid, a.thread_uid, t.post_op_number, a.hits, a.misses, a.last_hit_at
			FROM (
				SELECT board_uid, thread_uid, SUM(hits) AS hits, SUM(misses) AS misses, MAX(last_hit_at) AS last_hit_at
				FROM {$this->table}
				{$boardFilter}
				GROUP BY board_uid, thread_uid
			) a
			INNER JOIN {$this->threadTable} t ON t.thread_uid = a.thread_uid AND t.boardUID = a.board_uid
			ORDER BY a.hits DESC, a.last_hit_at DESC";
		$this->paginate($query, $params, max(1, $limit));

		return $this->queryAll($query, $params);
	}
}
