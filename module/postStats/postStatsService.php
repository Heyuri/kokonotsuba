<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';
require_once __DIR__ . '/postStatsBuildQueue.php';
require_once __DIR__ . '/postStatsLedger.php';

use function Puchiko\createDirectory;

/**
 * Builds the daily post series for a board and for the site, and keeps it in a JSON file.
 *
 * A post number is handed out once and never reused, so the total is the counter's value and
 * purged posts still count. Each number is put on the day the evidence proves it was made, or
 * counted as undated when it cannot be: see datePosts(). Nothing is estimated.
 *
 * Completed days are committed to the cache once and never rewritten, so what was provable when
 * a day closed stays proven even if its posts are purged later. Each view reads only the runs
 * past the committed cut, which is today's posts and little else.
 *
 * The first build reads a board's whole history; with a build queue it is handed off rather
 * than run inside the page view.
 */
class postStatsService {
	/** Bump whenever the meaning of a cached figure changes; older caches are rebuilt. */
	private const CACHE_VERSION = 7;

	private string $today = '';
	private int $secondsToday = 0;
	private array $currentNumbers = [];

	public function __construct(
		private readonly postStatsRepository $repository,
		private readonly string $cacheDirectory,
		private readonly ?postStatsBuildQueue $buildQueue = null,
	) {}

	/**
	 * Today's date and the current post number for each of $boardUids, memoised across calls.
	 * A reading of each counter is recorded on the way past.
	 */
	private function snapshot(array $boardUids): array {
		$missing = array_values(array_diff($boardUids, array_keys($this->currentNumbers)));

		if ($this->today === '' || $missing) {
			$snapshot = $this->repository->getSnapshot($missing ?: $boardUids);
			$this->today = $snapshot['today'];
			$this->secondsToday = $snapshot['secondsToday'] ?? 0;
			$this->currentNumbers += $snapshot['numbers'];

			foreach ($missing as $uid) {
				$this->currentNumbers[$uid] ??= 0;
			}

			if ($snapshot['numbers']) {
				$this->repository->recordCounterHistory($snapshot['numbers'], $this->today);
			}
		}

		return $this->currentNumbers;
	}

	/**
	 * Daily post counts and totals for a single board.
	 *
	 * @return array See getStats().
	 */
	public function getBoardStats(int $boardUid, string $startDay = ''): array {
		return $this->getStats('board-' . $boardUid, [$boardUid], [$boardUid => $startDay]);
	}

	/**
	 * The same over several boards, with each board's own series and summary.
	 *
	 * @param int[]  $boardUids Boards to include.
	 * @param array  $startDays [uid => creation day].
	 */
	public function getSiteStats(array $boardUids, array $startDays = []): array {
		return $this->getStats('site', $this->normaliseUids($boardUids), $startDays);
	}

	/** Build a board's cache from nothing, whatever it costs. Called by the background task. */
	public function rebuildBoard(int $boardUid, string $startDay = ''): void {
		$this->rebuild('board-' . $boardUid, [$boardUid], [$boardUid => $startDay]);
	}

	/** As above, for the site-wide series. */
	public function rebuildSite(array $boardUids, array $startDays = []): void {
		$this->rebuild('site', $this->normaliseUids($boardUids), $startDays);
	}

	private function normaliseUids(array $boardUids): array {
		$boardUids = array_values(array_unique(array_map('intval', $boardUids)));
		sort($boardUids);

		return $boardUids;
	}

	private function rebuild(string $scope, array $boardUids, array $startDays): void {
		$this->snapshot($boardUids);
		$this->load($scope, $boardUids, $startDays, true);
	}

	/**
	 * @return array ['days' => [day => posts], 'dayList', 'series' => [uid => counts by dayList position],
	 *               'boards' => [uid => summary], 'ranked', 'firstNo', 'firstDay', 'startDay', 'lastNo',
	 *               'total', 'undated', 'today', 'todayCount', 'secondsToday', 'generating']
	 */
	private function getStats(string $scope, array $boardUids, array $startDays): array {
		$this->snapshot($boardUids);

		$loaded = $this->load($scope, $boardUids, $startDays, false);
		if ($loaded === null) {
			return $this->pendingStats();
		}

		return $this->present(...$loaded);
	}

	/**
	 * Read the cache, commit any days that have closed since, and work out what is still open.
	 *
	 * @return array|null [cache, pending by uid], or null while the first build is queued.
	 */
	private function load(string $scope, array $boardUids, array $startDays, bool $fresh): ?array {
		$path = $this->cacheDirectory . $scope . '.json';
		$cache = $fresh ? null : $this->readCache($path);

		if (!$this->isUsableCache($cache, $boardUids)) {
			if (!$fresh && $this->buildQueue?->request($scope, $this->queueArgs($boardUids, $startDays))) {
				return null;
			}

			$cache = $this->emptyCache($boardUids);
		}

		$through = $this->previousDay($this->today);
		$cuts = [];
		foreach ($cache['boards'] as $uid => $state) {
			$cuts[(int)$uid] = $state['cutNo'];
		}

		$runsByBoard = [];
		foreach ($this->repository->getRuns($cuts) as $row) {
			$runsByBoard[(int)$row['board_uid']][] = [
				'first' => (int)$row['first_no'],
				'last' => (int)$row['last_no'],
				'day' => (string)$row['day'],
			];
		}

		$readings = [];
		foreach ($this->repository->getCounterHistory(array_keys($cuts)) as $row) {
			$readings[(int)$row['board_uid']][(string)$row['day']] = (int)$row['post_number'];
		}

		$before = json_encode($cache['boards']);
		$pending = [];

		foreach ($cache['boards'] as $uid => $state) {
			$uid = (int)$uid;
			$runs = $runsByBoard[$uid] ?? [];

			if ($state['firstNo'] === 0 && $runs) {
				$this->setFirstPost($cache['boards'][$uid], $runs);
			}

			if ($state['cutNo'] === 0 && $state['cutDay'] === null) {
				$state['cutDay'] = $this->openingDay($startDays[$uid] ?? '', $runs);
				$cache['boards'][$uid]['opening'] = $state['cutDay'] ?? '';
			}

			$dated = datePosts(
				$runs,
				$readings[$uid] ?? [],
				$state['cutNo'],
				$state['cutDay'],
				$this->currentNumbers[$uid] ?? 0,
				$this->today,
				$through
			);

			$committed = $dated['committed'];
			foreach ($committed['days'] as $day => $count) {
				$cache = $this->addToSeries($cache, $uid, $day, $count);
			}

			$cache['boards'][$uid]['cutNo'] = $committed['cutNo'];
			$cache['boards'][$uid]['cutDay'] = $committed['cutDay'];
			$cache['boards'][$uid]['undated'] += $committed['undated'];

			$pending[$uid] = $dated['pending'];
		}

		if ($fresh || $cache['through'] !== $through || json_encode($cache['boards']) !== $before) {
			$cache['through'] = $through;
			$this->writeCache($path, $cache);
		}

		return [$cache, $pending];
	}

	/**
	 * Where a board's sequence is known to start: its creation day, when no surviving post is
	 * older than that. Otherwise nothing bounds the earliest numbers and they stay undated.
	 */
	private function openingDay(string $startDay, array $runs): ?string {
		if ($startDay === '') {
			return null;
		}

		foreach ($runs as $run) {
			if ($run['day'] < $startDay) {
				return null;
			}
		}

		return $startDay;
	}

	/** The oldest surviving post, for the "first post" figure. */
	private function setFirstPost(array &$state, array $runs): void {
		$first = $runs[0];
		foreach ($runs as $run) {
			if ($run['day'] < $first['day']) {
				$first = $run;
			}
		}

		$state['firstNo'] = $first['first'];
		$state['firstDay'] = $first['day'];
	}

	/** Add a committed count to a board's series, which is indexed by days since the origin. */
	private function addToSeries(array $cache, int $uid, string $day, int $count): array {
		if ($count <= 0) {
			return $cache;
		}

		if ($cache['origin'] === '') {
			$cache['origin'] = $day;
		} elseif ($day < $cache['origin']) {
			$shift = $this->dayOffset($day, $cache['origin']);
			foreach ($cache['series'] as $seriesUid => $series) {
				$cache['series'][$seriesUid] = array_merge(array_fill(0, $shift, 0), $series);
			}
			$cache['origin'] = $day;
		}

		$offset = $this->dayOffset($cache['origin'], $day);
		$series = $cache['series'][$uid] ?? [];
		if (count($series) <= $offset) {
			$series = array_pad($series, $offset + 1, 0);
		}
		$series[$offset] += $count;
		$cache['series'][$uid] = $series;

		return $cache;
	}

	/** Committed and pending figures together, in the shape the page draws from. */
	private function present(array $cache, array $pending): array {
		$today = $this->today;

		$origin = $cache['origin'] !== '' ? $cache['origin'] : $today;
		foreach ($pending as $open) {
			foreach (array_keys($open['days']) as $day) {
				$origin = min($origin, (string)$day);
			}
		}

		$length = $this->dayOffset($origin, $today) + 1;
		$shift = $cache['origin'] !== '' ? $this->dayOffset($origin, $cache['origin']) : 0;

		$dayList = [];
		$at = utcDay($origin)->getTimestamp();
		for ($index = 0; $index < $length; $index++) {
			$dayList[] = gmdate('Y-m-d', $at + $index * 86400);
		}

		$series = [];
		$boards = [];
		$totals = array_fill(0, $length, 0);
		$summary = ['total' => 0, 'undated' => 0, 'todayCount' => 0, 'firstDay' => '', 'firstNo' => 0, 'startDay' => ''];

		foreach ($cache['boards'] as $uid => $state) {
			$uid = (int)$uid;
			$row = array_fill(0, $length, 0);

			foreach ($cache['series'][$uid] ?? [] as $index => $count) {
				$row[$index + $shift] = $count;
			}
			foreach ($pending[$uid]['days'] ?? [] as $day => $count) {
				$row[$this->dayOffset($origin, (string)$day)] += $count;
			}

			$firstDated = '';
			foreach ($row as $index => $count) {
				$totals[$index] += $count;
				if ($firstDated === '' && $count > 0) {
					$firstDated = $dayList[$index];
				}
			}

			$counter = $this->currentNumbers[$uid] ?? 0;
			$startDay = $state['opening'] !== '' ? $state['opening'] : $firstDated;

			$series[$uid] = $row;
			$boards[$uid] = [
				'firstNo' => $state['firstNo'],
				'firstDay' => $state['firstDay'],
				'startDay' => $startDay,
				'lastNo' => $counter,
				'total' => max(0, $counter),
				'undated' => $state['undated'] + ($pending[$uid]['undated'] ?? 0),
				'todayCount' => $row[$length - 1],
			];

			$summary['total'] += $boards[$uid]['total'];
			$summary['undated'] += $boards[$uid]['undated'];
			$summary['todayCount'] += $boards[$uid]['todayCount'];

			if ($state['firstDay'] !== '' && ($summary['firstDay'] === '' || $state['firstDay'] < $summary['firstDay'])) {
				$summary['firstDay'] = $state['firstDay'];
				$summary['firstNo'] = $state['firstNo'];
			}
			if ($startDay !== '' && ($summary['startDay'] === '' || $startDay < $summary['startDay'])) {
				$summary['startDay'] = $startDay;
			}
		}

		$days = [];
		foreach ($totals as $index => $count) {
			if ($count > 0) {
				$days[$dayList[$index]] = $count;
			}
		}

		$single = count($boards) === 1 ? reset($boards) : null;

		return [
			'days' => $days,
			'dayList' => $dayList,
			'series' => $series,
			'boards' => $boards,
			'ranked' => $this->rankBoards(array_keys($boards)),
			'firstNo' => $summary['firstNo'],
			'firstDay' => $summary['firstDay'],
			'startDay' => $summary['startDay'],
			'lastNo' => $single['lastNo'] ?? 0,
			'total' => $summary['total'],
			'undated' => $summary['undated'],
			'today' => $today,
			'todayCount' => $summary['todayCount'],
			'secondsToday' => $this->secondsToday,
			'generating' => false,
		];
	}

	/** What a scope looks like while its first build is still running. */
	private function pendingStats(): array {
		return [
			'days' => [],
			'dayList' => [],
			'series' => [],
			'boards' => [],
			'ranked' => [],
			'firstNo' => 0,
			'firstDay' => '',
			'startDay' => '',
			'lastNo' => 0,
			'total' => 0,
			'undated' => 0,
			'today' => $this->today,
			'todayCount' => 0,
			'secondsToday' => $this->secondsToday,
			'generating' => true,
		];
	}

	private function queueArgs(array $boardUids, array $startDays): array {
		if (count($boardUids) === 1) {
			return [
				'boardUid' => $boardUids[0],
				'startDay' => $startDays[$boardUids[0]] ?? '',
				'cacheDirectory' => $this->cacheDirectory,
			];
		}

		return ['siteBoardUids' => $boardUids, 'startDays' => $startDays, 'cacheDirectory' => $this->cacheDirectory];
	}

	/**
	 * Every board, largest first. Ranked on lifetime posts so a colour keeps meaning the same
	 * board whichever range is shown.
	 */
	private function rankBoards(array $boardUids): array {
		$totals = [];
		foreach ($boardUids as $uid) {
			$totals[$uid] = $this->currentNumbers[$uid] ?? 0;
		}

		arsort($totals);

		return array_keys($totals);
	}

	private function emptyCache(array $boardUids): array {
		$boards = [];
		foreach ($boardUids as $uid) {
			$boards[$uid] = [
				'cutNo' => 0,
				'cutDay' => null,
				'undated' => 0,
				'opening' => '',
				'firstNo' => 0,
				'firstDay' => '',
			];
		}

		return [
			'version' => self::CACHE_VERSION,
			'through' => '',
			'origin' => '',
			'boards' => $boards,
			'series' => [],
		];
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

		if (@file_put_contents($temporaryPath, json_encode($cache)) === false) {
			error_log('postStats: could not write ' . $temporaryPath . ', statistics will be rebuilt on every view.');
			return;
		}

		if (!@rename($temporaryPath, $path)) {
			error_log('postStats: could not move ' . $temporaryPath . ' into place.');
			@unlink($temporaryPath);
		}
	}

	private function isUsableCache(?array $cache, array $boardUids): bool {
		if ($cache === null
			|| ($cache['version'] ?? 0) !== self::CACHE_VERSION
			|| !isset($cache['through'], $cache['origin'])
			|| !is_array($cache['boards'] ?? null)
			|| !is_array($cache['series'] ?? null)
		) {
			return false;
		}

		// A board appearing or disappearing changes every total, so start over.
		$cachedUids = array_map('intval', array_keys($cache['boards']));
		sort($cachedUids);

		return $cachedUids === $boardUids;
	}

	/** Whole days from $from to $to. Stepped in UTC so daylight saving cannot skew it. */
	private function dayOffset(string $from, string $to): int {
		return intdiv(utcDay($to)->getTimestamp() - utcDay($from)->getTimestamp(), 86400);
	}

	private function previousDay(string $day): string {
		return utcDay($day)->modify('-1 day')->format('Y-m-d');
	}
}
