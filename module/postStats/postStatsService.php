<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';
require_once __DIR__ . '/postStatsBuildQueue.php';

use function Puchiko\createDirectory;

/**
 * Builds the daily post series for a board and for the site, and keeps it in a JSON file.
 *
 * Nothing here counts rows. A post number is handed out once and never reused, so the arithmetic
 * is done entirely on the sequence: the total is the counter's current value, and a day's figure
 * is the distance the sequence moved during it. Purging a post removes its row but not its place
 * in the sequence, so purged posts — spam sweeps included — still count as posts that were made.
 *
 * The one thing the sequence cannot say is *when* a purged post was made, since the timestamp
 * went with the row. If every post of a day is purged, that day has no surviving evidence at all
 * and its share of the sequence lands on the next day that does. Nothing is lost from the totals;
 * it is attributed a day or so late.
 *
 * A completed day can never change, so it is computed once and then only appended to: the cache
 * records the day it has looked up to, and a view on a later day extends it with the days in
 * between. Today is never cached — it is one cheap lookup of the current post number against the
 * cached end-of-yesterday boundary, so the page is always current without a daily rebuild being
 * triggered by every view.
 *
 * That leaves exactly one query whose cost grows with the size of the board rather than with a
 * day's traffic: the very first build, which reads the whole history. When a build queue is
 * supplied that one is handed to it instead of being run inside the page view, and the page says
 * so rather than sitting there.
 */
class postStatsService {
	/**
	 * Bump whenever the meaning of a cached figure changes, not just its shape. A cache written
	 * by an older version is discarded and rebuilt rather than read under new rules.
	 *
	 * 2: days count from the start of the numbering rather than from the first surviving post,
	 *    and totals come from the counter — so purged posts count.
	 * 3: the site cache keeps each board's own day counts, so the site chart can show which
	 *    boards a day's activity came from.
	 * 4: those per-board counts are stored positionally against the shared day list rather than
	 *    as a date-keyed map per board.
	 */
	private const CACHE_VERSION = 4;

	/** Today's date and the latest post numbers, fetched once and shared by every scope. */
	private string $today = '';
	private array $currentNumbers = [];

	public function __construct(
		private readonly postStatsRepository $repository,
		private readonly string $cacheDirectory,
		private readonly ?postStatsBuildQueue $buildQueue = null,
	) {}

	/**
	 * Today's date and the current post number for each of $boardUids.
	 *
	 * Memoised across calls: a page showing a board and the site-wide totals asks for overlapping
	 * board sets, and there is no reason for either to make its own round trip. Only boards not
	 * already seen are fetched.
	 */
	private function snapshot(array $boardUids): array {
		$missing = array_values(array_diff($boardUids, array_keys($this->currentNumbers)));

		if ($this->today === '' || $missing) {
			$snapshot = $this->repository->getSnapshot($missing ?: $boardUids);
			$this->today = $snapshot['today'];
			$this->currentNumbers += $snapshot['numbers'];

			// Boards the counter table has no row for are settled here rather than re-queried.
			foreach ($missing as $uid) {
				$this->currentNumbers[$uid] ??= 0;
			}
		}

		return $this->currentNumbers;
	}

	/**
	 * Daily post counts and totals for a single board.
	 *
	 * @return array ['days' => ['Y-m-d' => int], 'firstNo', 'firstDay', 'lastNo', 'total', 'today', 'todayCount', 'generating']
	 */
	public function getBoardStats(int $boardUid): array {
		$currentNo = $this->snapshot([$boardUid])[$boardUid] ?? 0;
		$today = $this->today;
		$path = $this->cacheDirectory . 'board-' . $boardUid . '.json';
		$cache = $this->readCache($path);

		if (!$this->isUsableBoardCache($cache, $boardUid)) {
			if ($this->buildQueue?->request('board-' . $boardUid, ['boardUid' => $boardUid, 'cacheDirectory' => $this->cacheDirectory])) {
				return $this->pendingStats($today);
			}

			$cache = $this->buildBoardCache($boardUid, $today);
			$this->writeCache($path, $cache);
		} elseif ($cache['through'] !== $this->previousDay($today)) {
			$cache = $this->extendBoardCache($cache, $today);
			$this->writeCache($path, $cache);
		}

		$todayCount = max(0, $currentNo - $cache['boundary']);
		$days = $cache['days'];

		if ($todayCount > 0 || $cache['firstDay'] !== '') {
			$days[$today] = $todayCount;
		}

		return [
			'days' => $days,
			'firstNo' => $cache['firstNo'],
			'firstDay' => $cache['firstDay'],
			'lastNo' => $currentNo,
			// The counter is incremented once per post and never rolled back, so its current
			// value IS the number of posts ever made — no row has to survive for it to be right.
			'total' => max(0, $currentNo),
			'today' => $today,
			'todayCount' => $todayCount,
			'generating' => false,
		];
	}

	/**
	 * The same series summed over several boards, plus a per-board summary row for each.
	 *
	 * @param int[] $boardUids Boards to include.
	 * @return array Board-shaped stats with an extra 'boards' map keyed by board uid.
	 */
	public function getSiteStats(array $boardUids): array {
		$boardUids = array_values(array_unique(array_map('intval', $boardUids)));
		sort($boardUids);

		$currentNumbers = $this->snapshot($boardUids);
		$today = $this->today;
		$path = $this->cacheDirectory . 'site.json';
		$cache = $this->readCache($path);

		if (!$this->isUsableSiteCache($cache, $boardUids)) {
			if ($this->buildQueue?->request('site', ['siteBoardUids' => $boardUids, 'cacheDirectory' => $this->cacheDirectory])) {
				return $this->pendingStats($today) + ['boards' => []];
			}

			$cache = $this->buildSiteCache($boardUids, $today);
			$this->writeCache($path, $cache);
		} elseif ($cache['through'] !== $this->previousDay($today)) {
			$cache = $this->extendSiteCache($cache, $today);
			$this->writeCache($path, $cache);
		}

		$boards = [];
		$todayCount = 0;
		$total = 0;
		$firstDay = '';

		$series = [];

		foreach ($cache['boards'] as $uid => $board) {
			$uid = (int)$uid;
			$currentNo = $currentNumbers[$uid] ?? 0;
			$boardToday = max(0, $currentNo - $board['boundary']);
			$boardTotal = max(0, $currentNo);

			// Today is never cached, so it is appended to each board's series here — one more
			// position, matching the day appended to the shared list below.
			$row = $cache['series'][$uid] ?? [];
			$row[] = $boardToday;
			$series[$uid] = $row;

			$boards[$uid] = [
				'firstNo' => $board['firstNo'],
				'firstDay' => $board['firstDay'],
				'lastNo' => $currentNo,
				'total' => $boardTotal,
				'todayCount' => $boardToday,
			];

			$todayCount += $boardToday;
			$total += $boardTotal;

			if ($board['firstDay'] !== '' && ($firstDay === '' || $board['firstDay'] < $firstDay)) {
				$firstDay = $board['firstDay'];
			}
		}

		// Today closes both lists: one more day on the shared list, one more value on each board's
		// series, so the two stay the same length and the positions keep lining up.
		$days = $cache['days'];
		$days[$today] = $todayCount;

		return [
			'days' => $days,
			'firstNo' => 0,
			'firstDay' => $firstDay,
			'lastNo' => 0,
			'total' => $total,
			'today' => $today,
			'todayCount' => $todayCount,
			'generating' => false,
			// Board uids largest first — the order the chart hands out colours in.
			'ranked' => $this->rankBoards(array_keys($boards)),
			'dayList' => array_keys($days),
			'series' => $series,
			'boards' => $boards,
		];
	}

	// ─── Cache building ────────────────────────────────────────────

	/**
	 * Build a board's cache from nothing, whatever it costs. This is what the background task
	 * calls; page views go through getBoardStats(), which hands this off rather than waiting.
	 */
	public function rebuildBoard(int $boardUid): void {
		$this->snapshot([$boardUid]);

		$this->writeCache($this->cacheDirectory . 'board-' . $boardUid . '.json', $this->buildBoardCache($boardUid, $this->today));
	}

	/** As above, for the site-wide series. */
	public function rebuildSite(array $boardUids): void {
		$boardUids = array_values(array_unique(array_map('intval', $boardUids)));
		sort($boardUids);

		$this->snapshot($boardUids);

		$this->writeCache($this->cacheDirectory . 'site.json', $this->buildSiteCache($boardUids, $this->today));
	}

	/** What a scope looks like while its first build is still running. */
	private function pendingStats(string $today): array {
		return [
			'days' => [],
			'firstNo' => 0,
			'firstDay' => '',
			'lastNo' => 0,
			'total' => 0,
			'today' => $today,
			'todayCount' => 0,
			'generating' => true,
		];
	}

	private function buildBoardCache(int $boardUid, string $today): array {
		$rows = $this->repository->getDailySeries($boardUid);

		$cache = [
			'version' => self::CACHE_VERSION,
			'boardUid' => $boardUid,
			'through' => $this->previousDay($today),
			// The first surviving post of the first day is where the sequence starts, and it comes
			// back with the series rather than costing a query of its own.
			'firstNo' => $rows ? (int)$rows[0]['min_no'] : 0,
			'firstDay' => $rows ? (string)$rows[0]['day'] : '',
			'boundary' => 0,
			'days' => [],
		];

		return $this->collectBoardRows($cache, $rows);
	}

	private function extendBoardCache(array $cache, string $today): array {
		// The cached boundary goes into the query, so the server differences the first new day
		// against the real end of the cached history rather than against its own lowest post.
		$rows = $this->repository->getDailySeries(
			$cache['boardUid'],
			$this->nextDay($cache['through']),
			$cache['boundary']
		);

		$cache = $this->collectBoardRows($cache, $rows);
		$cache['through'] = $this->previousDay($today);

		// A board whose whole history was made today has no first post until the day turns over.
		if ($cache['firstDay'] === '' && $rows) {
			$cache['firstNo'] = (int)$rows[0]['min_no'];
			$cache['firstDay'] = (string)$rows[0]['day'];
		}

		return $cache;
	}

	/** Take the already-differenced rows onto a board cache. */
	private function collectBoardRows(array $cache, array $rows): array {
		foreach ($rows as $row) {
			$cache['days'][(string)$row['day']] = (int)$row['made'];
			$cache['boundary'] = max($cache['boundary'], (int)$row['max_no']);
		}

		return $cache;
	}

	/**
	 * Every board, largest first.
	 *
	 * Ranked on lifetime posts, which only ever grows, so the order is stable from one day to the
	 * next — which is what lets a colour keep meaning the same board however the chart is zoomed.
	 */
	private function rankBoards(array $boardUids): array {
		$totals = [];
		foreach ($boardUids as $uid) {
			$totals[$uid] = $this->currentNumbers[$uid] ?? 0;
		}

		arsort($totals);

		return array_keys($totals);
	}

	private function buildSiteCache(array $boardUids, string $today): array {
		$boards = [];
		foreach ($boardUids as $uid) {
			$boards[$uid] = ['firstNo' => 0, 'firstDay' => '', 'boundary' => 0];
		}

		$cache = [
			'version' => self::CACHE_VERSION,
			'through' => $this->previousDay($today),
			'boards' => $boards,
			'days' => [],
			'series' => [],
		];

		return $this->collectSiteRows($cache, $this->repository->getDailySeriesForBoards($boardUids));
	}

	private function extendSiteCache(array $cache, string $today): array {
		$boardUids = array_map('intval', array_keys($cache['boards']));
		$rows = $this->repository->getDailySeriesForBoards($boardUids, $this->nextDay($cache['through']));

		$cache = $this->collectSiteRows($cache, $rows);
		$cache['through'] = $this->previousDay($today);

		return $cache;
	}

	/**
	 * Sum the per-board rows into one shared day total.
	 *
	 * The server differences each board against its own previous day, but it does not know where
	 * a board's cached history left off — so the first row of each board is re-differenced here
	 * against the stored boundary when there is one. Rows arrive grouped by board, oldest first.
	 */
	private function collectSiteRows(array $cache, array $rows): array {
		$seen = [];
		$fresh = [];
		$perBoard = [];

		foreach ($rows as $row) {
			$uid = (int)$row['board_uid'];

			if (!isset($cache['boards'][$uid])) {
				continue;
			}

			$board = &$cache['boards'][$uid];
			$maxNo = (int)$row['max_no'];
			$day = (string)$row['day'];

			if (!isset($seen[$uid])) {
				$seen[$uid] = true;

				if ($board['firstDay'] === '') {
					$board['firstNo'] = (int)$row['min_no'];
					$board['firstDay'] = $day;
				}

				// Picks up whatever was made after the cached history ended, deletions included.
				$made = $board['boundary'] > 0 ? max(0, $maxNo - $board['boundary']) : (int)$row['made'];
			} else {
				$made = (int)$row['made'];
			}

			$board['boundary'] = max($board['boundary'], $maxNo);
			$fresh[$day] = ($fresh[$day] ?? 0) + $made;

			if ($made > 0) {
				$perBoard[$uid][$day] = $made;
			}

			unset($board);
		}

		return $this->appendDays($cache, $fresh, $perBoard);
	}

	/**
	 * Append a run of new days to the cache, keeping every board's series aligned to them.
	 *
	 * Each board's counts are held positionally against the shared day list rather than in a
	 * date-keyed map of its own. The dates are then stored once instead of once per board, and
	 * what is left is a flat list of integers — which is what keeps a site with many boards and
	 * a long history down to something a page view can afford to read.
	 *
	 * @param array $fresh    day => site total, for days not already in the cache.
	 * @param array $perBoard uid => [day => count] for the same days.
	 */
	private function appendDays(array $cache, array $fresh, array $perBoard): array {
		if (!$fresh) {
			return $cache;
		}

		ksort($fresh);

		// Days only ever arrive later than what is cached, so the shared list stays chronological
		// and the existing positions keep their meaning.
		$existing = count($cache['days']);
		$newDays = array_keys($fresh);

		foreach ($fresh as $day => $total) {
			$cache['days'][$day] = $total;
		}

		foreach (array_keys($cache['boards']) as $uid) {
			$series = $cache['series'][$uid] ?? [];

			// A board with no history yet starts as zeros for the days it missed.
			if (count($series) < $existing) {
				$series = array_pad($series, $existing, 0);
			}

			foreach ($newDays as $day) {
				$series[] = $perBoard[$uid][$day] ?? 0;
			}

			$cache['series'][$uid] = $series;
		}

		return $cache;
	}

	// ─── Cache file handling ───────────────────────────────────────

	private function readCache(string $path): ?array {
		if (!is_readable($path)) {
			return null;
		}

		$raw = file_get_contents($path);
		if ($raw === false) {
			return null;
		}

		$cache = json_decode($raw, true);

		return is_array($cache) ? $cache : null;
	}

	/** Written to a temporary file and renamed so a concurrent view never reads a half-file. */
	private function writeCache(string $path, array $cache): void {
		createDirectory($this->cacheDirectory);

		$temporaryPath = $path . '.' . getmypid() . '.tmp';

		if (file_put_contents($temporaryPath, json_encode($cache)) === false) {
			return;
		}

		if (!rename($temporaryPath, $path)) {
			unlink($temporaryPath);
		}
	}

	private function isUsableBoardCache(?array $cache, int $boardUid): bool {
		return $cache !== null
			&& ($cache['version'] ?? 0) === self::CACHE_VERSION
			&& ($cache['boardUid'] ?? null) === $boardUid
			&& isset($cache['through'], $cache['boundary'], $cache['firstNo'], $cache['firstDay'])
			&& is_array($cache['days'] ?? null);
	}

	private function isUsableSiteCache(?array $cache, array $boardUids): bool {
		if ($cache === null
			|| ($cache['version'] ?? 0) !== self::CACHE_VERSION
			|| !isset($cache['through'])
			|| !is_array($cache['days'] ?? null)
			|| !is_array($cache['boards'] ?? null)
		) {
			return false;
		}

		// A board appearing or disappearing changes every site total, so start over.
		$cachedUids = array_map('intval', array_keys($cache['boards']));
		sort($cachedUids);

		return $cachedUids === $boardUids && is_array($cache['series'] ?? null);
	}

	/** Stepped in UTC so a daylight-saving change cannot shorten or repeat a day. */
	private function previousDay(string $day): string {
		return utcDay($day)->modify('-1 day')->format('Y-m-d');
	}

	private function nextDay(string $day): string {
		return utcDay($day)->modify('+1 day')->format('Y-m-d');
	}
}
