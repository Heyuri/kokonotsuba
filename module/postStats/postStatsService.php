<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';
require_once __DIR__ . '/postStatsBuildQueue.php';
require_once __DIR__ . '/postStatsSpread.php';

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
 * went with the row. So the days are built from readings of the counter — the board's creation,
 * every day that still has posts on it, every recorded reading, and today — and the numbers
 * between two readings are spread across the days they must have fallen in. See spreadReadings().
 * Totals stay exact; only the attribution of a pruned stretch is estimated.
 *
 * A reading of today's counter is recorded on the way past, so days from here on are anchored by
 * something pruning cannot erase, and the estimating is only ever needed for history.
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
	 * 5: days come from spreading counter readings rather than from the surviving posts alone, so
	 *    a pruned stretch no longer piles onto one day.
	 * 6: the counter closes the run as well as opening it, so the stretch between the last
	 *    surviving post and now is spread too instead of landing on today. The stored boundary
	 *    means something different under this rule, so caches written under 5 are discarded.
	 */
	private const CACHE_VERSION = 6;

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

			// Leave a mark for today on the way past. Once a day has a reading of its own, no
			// amount of later pruning can take that day's activity away.
			if ($snapshot['numbers']) {
				$this->repository->recordCounterHistory($snapshot['numbers'], $this->today);
			}
		}

		return $this->currentNumbers;
	}

	/**
	 * Daily post counts and totals for a single board.
	 *
	 * @return array ['days' => ['Y-m-d' => int], 'firstNo', 'firstDay', 'lastNo', 'total', 'today', 'todayCount', 'generating']
	 */
	public function getBoardStats(int $boardUid, string $startDay = ''): array {
		$currentNo = $this->snapshot([$boardUid])[$boardUid] ?? 0;
		$today = $this->today;
		$path = $this->cacheDirectory . 'board-' . $boardUid . '.json';
		$cache = $this->readCache($path);

		if (!$this->isUsableBoardCache($cache, $boardUid)) {
			if ($this->buildQueue?->request('board-' . $boardUid, [
				'boardUid' => $boardUid,
				'startDay' => $startDay,
				'cacheDirectory' => $this->cacheDirectory,
			])) {
				return $this->pendingStats($today);
			}

			$cache = $this->buildBoardCache($boardUid, $today, $startDay);
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
			// Where the chart starts: the board's creation, or its oldest post when that is older.
			'startDay' => $cache['startDay'] ?: $cache['firstDay'],
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
	public function getSiteStats(array $boardUids, array $startDays = []): array {
		$boardUids = array_values(array_unique(array_map('intval', $boardUids)));
		sort($boardUids);

		$currentNumbers = $this->snapshot($boardUids);
		$today = $this->today;
		$path = $this->cacheDirectory . 'site.json';
		$cache = $this->readCache($path);

		if (!$this->isUsableSiteCache($cache, $boardUids)) {
			if ($this->buildQueue?->request('site', [
				'siteBoardUids' => $boardUids,
				'startDays' => $startDays,
				'cacheDirectory' => $this->cacheDirectory,
			])) {
				return $this->pendingStats($today) + ['boards' => []];
			}

			$cache = $this->buildSiteCache($boardUids, $today, $startDays);
			$this->writeCache($path, $cache);
		} elseif ($cache['through'] !== $this->previousDay($today)) {
			$cache = $this->extendSiteCache($cache, $today);
			$this->writeCache($path, $cache);
		}

		$boards = [];
		$todayCount = 0;
		$total = 0;
		$firstDay = '';
		$startDay = '';

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
				'startDay' => ($board['startDay'] ?? '') ?: $board['firstDay'],
				'lastNo' => $currentNo,
				'total' => $boardTotal,
				'todayCount' => $boardToday,
			];

			$todayCount += $boardToday;
			$total += $boardTotal;

			if ($board['firstDay'] !== '' && ($firstDay === '' || $board['firstDay'] < $firstDay)) {
				$firstDay = $board['firstDay'];
			}

			$boardStart = $boards[$uid]['startDay'];
			if ($boardStart !== '' && ($startDay === '' || $boardStart < $startDay)) {
				$startDay = $boardStart;
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
			// The site's history starts with its oldest board.
			'startDay' => $startDay ?: $firstDay,
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
	public function rebuildBoard(int $boardUid, string $startDay = ''): void {
		$this->snapshot([$boardUid]);

		$this->writeCache(
			$this->cacheDirectory . 'board-' . $boardUid . '.json',
			$this->buildBoardCache($boardUid, $this->today, $startDay)
		);
	}

	/** As above, for the site-wide series. */
	public function rebuildSite(array $boardUids, array $startDays = []): void {
		$boardUids = array_values(array_unique(array_map('intval', $boardUids)));
		sort($boardUids);

		$this->snapshot($boardUids);

		$this->writeCache(
			$this->cacheDirectory . 'site.json',
			$this->buildSiteCache($boardUids, $this->today, $startDays)
		);
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
			'startDay' => '',
			'generating' => true,
		];
	}

	private function buildBoardCache(int $boardUid, string $today, string $startDay = ''): array {
		$rows = $this->repository->getDailySeries($boardUid);

		$cache = [
			'version' => self::CACHE_VERSION,
			'boardUid' => $boardUid,
			'through' => $this->previousDay($today),
			// The first surviving post is where the board's remaining history starts, and it comes
			// back with the series rather than costing a query of its own. It is what the page
			// reports as "first post"; the readings below decide where the *chart* starts.
			'firstNo' => $rows ? (int)$rows[0]['min_no'] : 0,
			'firstDay' => $rows ? (string)$rows[0]['day'] : '',
			'boundary' => 0,
			'startDay' => '',
			'days' => [],
		];

		$readings = $this->openingReading($startDay, $rows);
		$cache['startDay'] = $readings ? (string)array_key_first($readings) : '';

		$readings = $this->addReadings($readings, $rows, $this->recordedReadings($boardUid, $today));

		return $this->applyReadings($cache, $readings, $today, $this->currentNumbers[$boardUid] ?? 0);
	}

	private function extendBoardCache(array $cache, string $today): array {
		$from = $this->nextDay($cache['through']);
		$rows = $this->repository->getDailySeries($cache['boardUid'], $from);

		// The cached history's end is the opening reading, so the first new day is measured from
		// where the last build left off rather than from its own lowest surviving post.
		$readings = [$cache['through'] => $cache['boundary']];
		$readings = $this->addReadings($readings, $rows, $this->recordedReadings($cache['boardUid'], $today, $from));

		$cache = $this->applyReadings($cache, $readings, $today, $this->currentNumbers[$cache['boardUid']] ?? 0);
		$cache['through'] = $this->previousDay($today);

		// A board whose whole history was made today has no first post until the day turns over.
		if ($cache['firstDay'] === '' && $rows) {
			$cache['firstNo'] = (int)$rows[0]['min_no'];
			$cache['firstDay'] = (string)$rows[0]['day'];
		}

		return $cache;
	}

	/**
	 * Where a board's sequence is known to have started.
	 *
	 * The day it was created, when it is known — the counter stood at nothing then, so everything
	 * since is accounted for. Without it there is no lower bound on when the pruned posts were
	 * made, and the best that can be said is that the first surviving day holds its own posts and
	 * no more; the numbers before it are left off the chart rather than dropped onto it.
	 */
	private function openingReading(string $startDay, array $rows): array {
		$firstDay = $rows ? (string)$rows[0]['day'] : '';

		// Created before any of its surviving posts: the counter stood at nothing that day, and
		// everything since is accounted for between there and now.
		if ($startDay !== '' && ($firstDay === '' || $startDay < $firstDay)) {
			return [$startDay => 0];
		}

		if ($firstDay === '') {
			return $startDay !== '' ? [$startDay => 0] : [];
		}

		// The opening reading has to sit on its own day, or it merges with the first surviving
		// day's and there is nothing to measure between.
		$opening = $this->previousDay($firstDay);

		// Created the same day it was first posted on: nothing came before, so the sequence
		// really did start at zero.
		if ($startDay === $firstDay) {
			return [$opening => 0];
		}

		// Otherwise the board is older than its own row says, or has no creation date at all.
		// Either way there is no lower bound on when the numbers below its oldest surviving post
		// were made, so the first surviving day is credited with its own posts and no more. The
		// alternative — calling the sequence zero the day before — is the pile-up this whole
		// approach exists to avoid, and it would be inventing a date rather than admitting there
		// isn't one.
		return [$opening => max(0, (int)$rows[0]['min_no'] - 1)];
	}

	/** Fold surviving-post maxima and recorded counter readings into one set of readings. */
	private function addReadings(array $readings, array $rows, array $recorded): array {
		foreach ($rows as $row) {
			$day = (string)$row['day'];
			$readings[$day] = max($readings[$day] ?? 0, (int)$row['max_no']);
		}

		foreach ($recorded as $day => $value) {
			$readings[$day] = max($readings[$day] ?? 0, $value);
		}

		return $readings;
	}

	/** Recorded counter readings for completed days only; today is never cached. */
	private function recordedReadings(int $boardUid, string $today, string $from = ''): array {
		$readings = [];

		foreach ($this->repository->getCounterHistory([$boardUid]) as $row) {
			$day = (string)$row['day'];

			if ($day >= $today || ($from !== '' && $day < $from)) {
				continue;
			}

			$readings[$day] = (int)$row['post_number'];
		}

		return $readings;
	}

	/**
	 * Spread the readings into days and take them onto the cache.
	 *
	 * The counter closes the run. Without it the days stop at the last post still on disk and
	 * everything since — which on a pruned board can be years of it — has nowhere to go but
	 * today, which is the same pile-up as the opening one, just at the other end.
	 *
	 * Today itself is never cached: what is stored is where the sequence had got to by the end of
	 * yesterday, so today's figure stays live as the day runs.
	 */
	private function applyReadings(array $cache, array $readings, string $today, int $counter): array {
		if ($counter > 0) {
			$readings[$today] = max($readings[$today] ?? 0, $counter);
		}

		$spread = spreadReadings($readings);
		$todayShare = $spread[$today] ?? 0;
		unset($spread[$today]);

		foreach ($spread as $day => $made) {
			$cache['days'][$day] = $made;
		}

		$cache['boundary'] = max($cache['boundary'], $counter - $todayShare);

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

	private function buildSiteCache(array $boardUids, string $today, array $startDays = []): array {
		$boards = [];
		foreach ($boardUids as $uid) {
			$boards[$uid] = ['firstNo' => 0, 'firstDay' => '', 'boundary' => 0, 'startDay' => ''];
		}

		$cache = [
			'version' => self::CACHE_VERSION,
			'through' => $this->previousDay($today),
			'boards' => $boards,
			'days' => [],
			'series' => [],
		];

		return $this->collectSiteRows(
			$cache,
			$this->repository->getDailySeriesForBoards($boardUids),
			$today,
			$startDays
		);
	}

	private function extendSiteCache(array $cache, string $today): array {
		$boardUids = array_map('intval', array_keys($cache['boards']));
		$from = $this->nextDay($cache['through']);
		$rows = $this->repository->getDailySeriesForBoards($boardUids, $from);

		$cache = $this->collectSiteRows($cache, $rows, $today, [], $from);
		$cache['through'] = $this->previousDay($today);

		return $cache;
	}

	/**
	 * Build each board's days from its own readings, then sum them into the shared series.
	 *
	 * Every board is spread separately — a pruned stretch on one says nothing about another — and
	 * only then added together, so the site total is the sum of the per-board estimates rather
	 * than an estimate made over the pile.
	 *
	 * @param string $from Earliest day being added, when this is extending an existing cache.
	 */
	private function collectSiteRows(array $cache, array $rows, string $today, array $startDays = [], string $from = ''): array {
		$byBoard = [];
		foreach ($rows as $row) {
			$uid = (int)$row['board_uid'];
			if (isset($cache['boards'][$uid])) {
				$byBoard[$uid][] = $row;
			}
		}

		$recorded = $this->recordedReadingsForBoards(array_map('intval', array_keys($cache['boards'])), $today, $from);

		$fresh = [];
		$perBoard = [];

		foreach ($cache['boards'] as $uid => $board) {
			$uid = (int)$uid;
			$boardRows = $byBoard[$uid] ?? [];

			if ($board['firstDay'] === '' && $boardRows) {
				$cache['boards'][$uid]['firstNo'] = (int)$boardRows[0]['min_no'];
				$cache['boards'][$uid]['firstDay'] = (string)$boardRows[0]['day'];
			}

			if ($from !== '') {
				$readings = [$cache['through'] => $board['boundary']];
			} else {
				$readings = $this->openingReading($startDays[$uid] ?? '', $boardRows);
				$cache['boards'][$uid]['startDay'] = $readings ? (string)array_key_first($readings) : '';
			}

			$readings = $this->addReadings($readings, $boardRows, $recorded[$uid] ?? []);

			$counter = $this->currentNumbers[$uid] ?? 0;
			if ($counter > 0) {
				$readings[$today] = max($readings[$today] ?? 0, $counter);
			}

			$spread = spreadReadings($readings);
			$cache['boards'][$uid]['boundary'] = max($board['boundary'], $counter - ($spread[$today] ?? 0));
			unset($spread[$today]);

			foreach ($spread as $day => $made) {
				if ($made <= 0) {
					continue;
				}

				$perBoard[$uid][$day] = $made;
				$fresh[$day] = ($fresh[$day] ?? 0) + $made;
			}
		}

		return $this->appendDays($cache, $fresh, $perBoard);
	}

	/** Recorded readings for several boards, keyed by uid then day. */
	private function recordedReadingsForBoards(array $boardUids, string $today, string $from = ''): array {
		$readings = [];

		foreach ($this->repository->getCounterHistory($boardUids) as $row) {
			$day = (string)$row['day'];

			if ($day >= $today || ($from !== '' && $day < $from)) {
				continue;
			}

			$readings[(int)$row['board_uid']][$day] = (int)$row['post_number'];
		}

		return $readings;
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

		// A cache that cannot be written is not a small problem: every view then rebuilds the
		// whole history from scratch, which looks like the page being slow for no reason. Say so
		// rather than failing quietly.
		if (@file_put_contents($temporaryPath, json_encode($cache)) === false) {
			error_log('postStats: could not write ' . $temporaryPath . ' — statistics will be rebuilt on every view.');
			return;
		}

		if (!@rename($temporaryPath, $path)) {
			error_log('postStats: could not move ' . $temporaryPath . ' into place.');
			@unlink($temporaryPath);
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
