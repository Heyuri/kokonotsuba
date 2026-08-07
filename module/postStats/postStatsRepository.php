<?php

namespace Kokonotsuba\Modules\postStats;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/**
 * Queries behind the post statistics page.
 *
 * Everything counts posts through the per-board `no` sequence rather than through row counts.
 * A number is handed out once per post and never reused, so the distance between two points in
 * the sequence is the number of posts *made* between them, including the ones deleted since.
 *
 * There are only two queries. One returns today's date together with every board's latest post
 * number; the other returns the whole daily series with the subtraction already applied by the
 * server, so a page view costs one round trip and a rebuild costs two.
 *
 * Day bucketing happens in the database (DATE(root), CURDATE()) so that the boundaries match
 * the timestamps as they are stored and read back everywhere else.
 */
class postStatsRepository extends baseRepository {
	public function __construct(
		databaseConnection $databaseConnection,
		string $postTable,
		private readonly string $postNumberTable,
	) {
		parent::__construct($databaseConnection, $postTable);
		self::validateTableNames($postNumberTable);
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
	 * The daily series for one board: how many posts were made on each completed day.
	 *
	 * The per-day high-water marks are differenced by the server with LAG, so what comes back is
	 * already the answer rather than something for PHP to subtract row by row.
	 *
	 * @param int      $boardUid Board to report on.
	 * @param string   $fromDay  Earliest day to include (Y-m-d), or '' for the whole history.
	 * @param int|null $boundary Highest number reached before $fromDay, when it is already known.
	 *                           Without it the first day counts from zero — the start of the
	 *                           board's numbering — so numbers whose posts have since been purged
	 *                           are still counted as posts that were made.
	 * @return array Rows of ['day', 'min_no', 'max_no', 'made'], oldest first.
	 */
	public function getDailySeries(int $boardUid, string $fromDay = '', ?int $boundary = null): array {
		$params = [':board' => $boardUid, ':boundary' => $boundary];
		$fromCondition = '';

		if ($fromDay !== '') {
			$fromCondition = ' AND root >= :fromDay';
			$params[':fromDay'] = $fromDay;
		}

		return $this->queryAll(
			"SELECT day, min_no, max_no,
			        GREATEST(0, max_no - COALESCE(LAG(max_no) OVER (ORDER BY day), :boundary, 0)) AS made
			 FROM (
			     SELECT DATE(root) AS day, MIN(`no`) AS min_no, MAX(`no`) AS max_no
			     FROM {$this->table}
			     WHERE boardUID = :board AND root < CURDATE(){$fromCondition}
			     GROUP BY day
			 ) AS daily
			 ORDER BY day",
			$params
		);
	}

	/**
	 * The same series for several boards at once, partitioned so each board is differenced
	 * against its own history rather than against whichever board happened to precede it.
	 *
	 * Per-board starting boundaries are not passed here — the caller holds them and corrects the
	 * first row of each board itself, which keeps this to one statement for any number of boards.
	 *
	 * @return array Rows of ['board_uid', 'day', 'min_no', 'max_no', 'made'], grouped by board.
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
			"SELECT board_uid, day, min_no, max_no,
			        GREATEST(0, max_no - COALESCE(LAG(max_no) OVER (PARTITION BY board_uid ORDER BY day), 0)) AS made
			 FROM (
			     SELECT boardUID AS board_uid, DATE(root) AS day, MIN(`no`) AS min_no, MAX(`no`) AS max_no
			     FROM {$this->table}
			     WHERE boardUID IN {$inClause} AND root < CURDATE(){$fromCondition}
			     GROUP BY board_uid, day
			 ) AS daily
			 ORDER BY board_uid, day",
			$params
		);
	}
}
