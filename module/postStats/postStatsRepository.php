<?php

namespace Kokonotsuba\Modules\postStats;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use Throwable;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/**
 * Queries behind the post statistics page.
 *
 * Everything counts posts through the per-board `no` sequence rather than through row counts.
 * A number is handed out once per post and never reused, so the distance between two points in
 * the sequence is the number of posts *made* between them, including the ones deleted since.
 *
 * A warm page view costs one round trip: today's date and every board's current counter come
 * back together. A rebuild adds the surviving posts' per-day high and low, and the recorded
 * counter readings. What those add up to per day is worked out in PHP, since it has to fill in
 * the days the rows no longer cover.
 *
 * Day bucketing happens in the database (DATE(root), CURDATE()) so that the boundaries match
 * the timestamps as they are stored and read back everywhere else.
 */
class postStatsRepository extends baseRepository {
	/** Set once per request if the readings table turns out not to be usable. */
	private static bool $historyUnavailable = false;

	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable,
		private readonly string $postNumberTable,
		private readonly string $postNumberHistoryTable = '',
	) {
		parent::__construct($databaseConnection, $postTable);
		self::validateTableNames($postNumberTable);

		if ($postNumberHistoryTable !== '') {
			self::validateTableNames($postNumberHistoryTable);
		}
	}

	/**
	 * Readings of each board's counter, by day.
	 *
	 * These are the only record of activity that pruning cannot erase: a post's row can go, but
	 * the reading taken that day still says where the sequence had got to. Where they exist the
	 * daily figures are exact; where they do not, the surviving posts are all there is to go on.
	 *
	 * @return array Rows of ['board_uid', 'day', 'post_number'].
	 */
	public function getCounterHistory(array $boardUids): array {
		if (!$boardUids || !$this->historyAvailable()) {
			return [];
		}

		$params = array_values($boardUids);
		$inClause = pdoPlaceholdersForIn($params);

		try {
			return $this->queryAll(
				"SELECT board_uid, day, post_number
				 FROM {$this->postNumberHistoryTable}
				 WHERE board_uid IN {$inClause}
				 ORDER BY board_uid, day",
				$params
			);
		} catch (Throwable $error) {
			$this->historyFailed($error);

			return [];
		}
	}

	/**
	 * Record where each board's counter stands today.
	 *
	 * Written on the way past rather than on a schedule: the last reading taken on a day becomes
	 * that day's closing figure. Posts made after it roll into the next day, which is a few hours
	 * of drift rather than the open-ended error that reading pruned history gives.
	 */
	public function recordCounterHistory(array $numbers, string $day): void {
		if (!$numbers || !$this->historyAvailable()) {
			return;
		}

		$values = [];
		$params = [];

		foreach ($numbers as $uid => $number) {
			$values[] = '(?, ?, ?)';
			$params[] = (int)$uid;
			$params[] = $day;
			$params[] = (int)$number;
		}

		try {
			$this->query(
				"INSERT INTO {$this->postNumberHistoryTable} (board_uid, day, post_number)
				 VALUES " . implode(', ', $values) . "
				 ON DUPLICATE KEY UPDATE post_number = GREATEST(post_number, VALUES(post_number))",
				$params
			);
		} catch (Throwable $error) {
			$this->historyFailed($error);
		}
	}

	/**
	 * Whether the readings table can be used.
	 *
	 * It is the one part of this that needs a table creating by hand, so an install that has the
	 * setting but not the table is a real possibility. That should cost the readings, not the
	 * page: without them the figures fall back to what the surviving posts say, which is what
	 * every install had before the table existed.
	 */
	private function historyAvailable(): bool {
		return $this->postNumberHistoryTable !== '' && !self::$historyUnavailable;
	}

	private function historyFailed(Throwable $error): void {
		if (!self::$historyUnavailable) {
			self::$historyUnavailable = true;
			error_log(
				'postStats: counter readings unavailable (' . $error->getMessage() . '). '
				. 'Statistics will fall back to surviving posts; create the '
				. $this->postNumberHistoryTable . ' table to record them.'
			);
		}
	}

	/**
	 * Today's date and the latest number handed out per board.
	 *
	 * The numbers come from the counter table rather than from MAX(no), so they survive the tail
	 * of a board being deleted — which is the case the page exists to report honestly. The date
	 * rides along on the same round trip because every caller needs both.
	 *
	 * @return array{today: string, numbers: array<int, int>}
	 */
	public function getSnapshot(array $boardUids): array {
		$boardUids = array_values($boardUids);

		// LEFT JOIN from a one-row derived table so the date still comes back for a board with
		// no counter row yet, or for no boards at all.
		$inClause = $boardUids ? pdoPlaceholdersForIn($boardUids) : '(NULL)';

		$rows = $this->queryAll(
			"SELECT CURDATE() AS today, counter.board_uid, counter.post_number
			 FROM (SELECT 1) AS present
			 LEFT JOIN {$this->postNumberTable} AS counter ON counter.board_uid IN {$inClause}",
			$boardUids
		);

		$numbers = [];
		foreach ($rows as $row) {
			if ($row['board_uid'] !== null) {
				$numbers[(int)$row['board_uid']] = (int)$row['post_number'];
			}
		}

		return ['today' => (string)($rows[0]['today'] ?? ''), 'numbers' => $numbers];
	}

	/**
	 * What each completed day's surviving posts say about where the sequence had got to.
	 *
	 * The lowest and highest number left on each day, which is all the evidence the rows can
	 * give. Turning that into per-day counts is the caller's job: it also has the board's
	 * creation date and the recorded readings to weigh in, and the days with nothing left on
	 * them have to be filled from the gaps between.
	 *
	 * @param int    $boardUid Board to report on.
	 * @param string $fromDay  Earliest day to include (Y-m-d), or '' for the whole history.
	 * @return array Rows of ['day', 'min_no', 'max_no'], oldest first.
	 */
	public function getDailySeries(int $boardUid, string $fromDay = ''): array {
		$params = [':board' => $boardUid];
		$fromCondition = '';

		if ($fromDay !== '') {
			$fromCondition = ' AND root >= :fromDay';
			$params[':fromDay'] = $fromDay;
		}

		return $this->queryAll(
			"SELECT DATE(root) AS day, MIN(`no`) AS min_no, MAX(`no`) AS max_no
			 FROM {$this->table}
			 WHERE boardUID = :board AND root < CURDATE(){$fromCondition}
			 GROUP BY day
			 ORDER BY day",
			$params
		);
	}

	/**
	 * The same evidence for several boards at once, in one statement whatever the board count.
	 *
	 * @return array Rows of ['board_uid', 'day', 'min_no', 'max_no'], grouped by board.
	 */
	public function getDailySeriesForBoards(array $boardUids, string $fromDay = ''): array {
		if (!$boardUids) {
			return [];
		}

		$params = array_values($boardUids);
		$inClause = pdoPlaceholdersForIn($params);
		$fromCondition = '';

		if ($fromDay !== '') {
			$fromCondition = ' AND root >= ?';
			$params[] = $fromDay;
		}

		return $this->queryAll(
			"SELECT boardUID AS board_uid, DATE(root) AS day, MIN(`no`) AS min_no, MAX(`no`) AS max_no
			 FROM {$this->table}
			 WHERE boardUID IN {$inClause} AND root < CURDATE(){$fromCondition}
			 GROUP BY board_uid, day
			 ORDER BY board_uid, day",
			$params
		);
	}
}
