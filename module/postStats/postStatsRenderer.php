<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';

use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;

/**
 * Turns a daily post series into the values the templates under templates/ are filled with.
 *
 * The series is always held per day; anything wider than the point budget (MAX_BARS) is bucketed into weeks
 * or months here, so the zoom links only change how the same cached numbers are drawn.
 *
 * Nothing in this class writes markup — it prepares values and hands them to a block. Anything
 * going into a template is escaped on the way, since the engine substitutes placeholders as they
 * are given.
 */
class postStatsRenderer {
	/**
	 * Selectable spans, in days. 0 is the whole history. Keys stay non-numeric so they survive
	 * as strings in the array, which numeric-looking keys would not.
	 *
	 * 'label' names a translation key rather than holding the text: a const cannot call _T().
	 */
	public const RANGES = [
		'30d' => ['days' => 30, 'label' => 'poststats_range_30d'],
		'90d' => ['days' => 90, 'label' => 'poststats_range_90d'],
		'1y' => ['days' => 365, 'label' => 'poststats_range_1y'],
		'all' => ['days' => 0, 'label' => 'poststats_range_all'],
	];

	/** Validated hues available before identity has to lean on the pattern as well. */
	public const HUES = 8;

	/** Solid, then the same hues at 45°, then at 135°. */
	public const TIERS = 3;

	/** Mean Gregorian month, so a monthly figure does not swing with the length of the month. */
	private const DAYS_PER_MONTH = 365.25 / 12;

	/** The plot's own coordinate space; it is stretched to fit, with strokes kept a fixed width. */
	private const PLOT_WIDTH = 1000;
	private const PLOT_HEIGHT = 100;

	/** Gives each stacked chart on a page its own pattern ids. */
	private static int $chartCount = 0;

	/** Most dates the x axis will carry before it starts skipping buckets. */
	private const AXIS_LABELS = 6;

	public function __construct(
		private readonly templateEngine $templateEngine,
		private readonly int $maxBars,
	) {}

	/**
	 * Expand a sparse day map into a continuous day-by-day series over the requested span.
	 *
	 * @param array  $days      Map of 'Y-m-d' => posts made.
	 * @param string $firstDay  Day the board or site starts on.
	 * @param string $today     Last day to include.
	 * @param int    $rangeDays Days to show back from today, or 0 for everything.
	 * @return array Ordered map of 'Y-m-d' => posts made, with the empty days filled in.
	 */
	public function buildSeries(array $days, string $firstDay, string $today, int $rangeDays): array {
		if ($firstDay === '') {
			return [];
		}

		$start = $firstDay;
		if ($rangeDays > 0) {
			$windowStart = utcDay($today)->modify('-' . ($rangeDays - 1) . ' days')->format('Y-m-d');
			$start = max($start, $windowStart);
		}

		if ($start > $today) {
			return [];
		}

		// Whole days of seconds rather than a DatePeriod: an all-time range on an old board is
		// thousands of steps, and this is on the page's critical path.
		$from = utcDay($start)->getTimestamp();
		$to = utcDay($today)->getTimestamp();

		$series = [];
		for ($at = $from; $at <= $to; $at += 86400) {
			$day = gmdate('Y-m-d', $at);
			$series[$day] = (int)($days[$day] ?? 0);
		}

		return $series;
	}

	/**
	 * Group a daily series down to something that fits the point budget.
	 *
	 * @return array Buckets of ['label', 'start', 'end', 'value', 'dayCount'].
	 */
	public function bucketSeries(array $series, string $today): array {
		$dayCount = count($series);

		if ($dayCount === 0) {
			return [];
		}

		$unit = $this->bucketUnit($dayCount);

		$buckets = [];
		foreach ($series as $day => $value) {
			$key = $this->bucketKey($day, $unit);

			if (!isset($buckets[$key])) {
				$buckets[$key] = ['label' => $key, 'start' => $day, 'end' => $day, 'value' => 0, 'dayCount' => 0];
			}

			$buckets[$key]['end'] = $day;
			$buckets[$key]['value'] += $value;
			$buckets[$key]['dayCount']++;
		}

		// The newest bucket is still filling up if it runs to today, and would otherwise read as
		// a collapse in activity next to the complete ones beside it.
		$buckets = array_values($buckets);
		$last = count($buckets) - 1;
		$buckets[$last]['partial'] = $buckets[$last]['end'] === $today;

		return $buckets;
	}

	/** How wide a bucket has to be for the series to fit the point budget. */
	private function bucketUnit(int $dayCount): string {
		if ($dayCount <= $this->maxBars) {
			return 'day';
		}

		return (int)ceil($dayCount / 7) <= $this->maxBars ? 'week' : 'month';
	}

	/**
	 * Which bucket a day falls in. Doubles as the bucket's label.
	 *
	 * The week is found by arithmetic rather than by asking a date object for "monday this week":
	 * this runs once per day per pass, twice over, and a year's range is a thousand calls.
	 */
	private function bucketKey(string $day, string $unit): string {
		if ($unit === 'day') {
			return $day;
		}

		if ($unit !== 'week') {
			return substr($day, 0, 7);
		}

		$epochDays = intdiv(utcDay($day)->getTimestamp(), 86400);

		// Day 0 was a Thursday, so +3 lands Monday on a multiple of seven.
		return gmdate('Y-m-d', ($epochDays - (($epochDays + 3) % 7)) * 86400);
	}

	/**
	 * The same buckets as bucketSeries(), with each one broken down by board.
	 *
	 * Bucketing is driven by the totals so every board lands in the same columns — a board's own
	 * series is only read for the days the range already covers.
	 *
	 * @param array $series  Gap-filled totals for the range, from buildSeries().
	 * @param array $dayList Every day the board series are indexed against, in order.
	 * @param array $boardSeries uid => counts, positionally aligned to $dayList.
	 * @return array bucketSeries() buckets, each with a 'segments' => [uid => value] map.
	 */
	public function bucketStack(array $series, array $dayList, array $boardSeries, string $today): array {
		$buckets = $this->bucketSeries($series, $today);

		if (!$buckets) {
			return [];
		}

		$unit = $this->bucketUnit(count($series));
		$indexByKey = [];

		foreach ($buckets as $index => $bucket) {
			$indexByKey[$bucket['label']] = $index;
			$buckets[$index]['segments'] = [];
		}

		// Work out once which bucket each position falls in, so the per-board pass below is
		// integer lookups rather than date arithmetic repeated for every board.
		$bucketAt = [];
		foreach ($dayList as $position => $day) {
			// A day outside the selected span belongs to no column.
			if (!array_key_exists($day, $series)) {
				continue;
			}

			$index = $indexByKey[$this->bucketKey($day, $unit)] ?? null;
			if ($index !== null) {
				$bucketAt[$position] = $index;
			}
		}

		foreach ($boardSeries as $uid => $counts) {
			foreach ($bucketAt as $position => $index) {
				$value = $counts[$position] ?? 0;
				if ($value > 0) {
					$buckets[$index]['segments'][$uid] = ($buckets[$index]['segments'][$uid] ?? 0) + $value;
				}
			}
		}

		return $buckets;
	}

	/**
	 * Give every board an identity of its own.
	 *
	 * There are only eight hues that stay apart under colourblindness, and inventing more is how
	 * a palette quietly stops working. Past the eighth board identity picks up a second channel
	 * instead: the same hues again, hatched at 45°, then at 135°. Hue and pattern together carry
	 * it, so two boards sharing a hue are still told apart without relying on colour.
	 *
	 * The order comes from the service, which ranks on lifetime posts rather than on a board's
	 * share of the span being viewed: a colour has to mean the same board whichever zoom is
	 * selected, so changing the range must never repaint anything.
	 *
	 * @param array $ranked Board uids, largest first, from getSiteStats().
	 * @param array $boards Board objects, for names and links.
	 * @return array [uid => ['hue', 'tier', 'label', 'url']]
	 */
	public function assignSeries(array $ranked, array $boards): array {
		$byUid = [];
		foreach ($boards as $board) {
			$byUid[$board->getBoardUID()] = $board;
		}

		$series = [];
		$slot = 0;

		foreach ($ranked as $uid) {
			if (!isset($byUid[$uid])) {
				continue;
			}

			$series[$uid] = [
				// Past hue × tier combinations the pairs repeat. That is 24 boards; the ones it
				// would affect are the quietest on the site, and the legend still names them.
				'hue' => (string)(($slot % self::HUES) + 1),
				'tier' => (string)(intdiv($slot, self::HUES) % self::TIERS),
				'order' => $slot,
				'label' => $byUid[$uid]->getBoardTitle(),
				'url' => (string)$byUid[$uid]->getBoardURL(),
			];

			$slot++;
		}

		return $series;
	}

	/**
	 * Dates for the x axis, evenly spaced across the buckets.
	 *
	 * The labels are spread edge to edge as the points are, so taking evenly spaced buckets
	 * keeps each date under the point it belongs to. Short series get a label per bucket.
	 */
	private function axisLabels(array $buckets): array {
		$count = count($buckets);

		if ($count <= self::AXIS_LABELS) {
			$indices = range(0, $count - 1);
		} else {
			$indices = [];
			for ($i = 0; $i < self::AXIS_LABELS; $i++) {
				$indices[] = (int)round($i * ($count - 1) / (self::AXIS_LABELS - 1));
			}
			$indices = array_values(array_unique($indices));
		}

		return array_map(
			fn($index) => ['{$LABEL}' => htmlspecialchars($buckets[$index]['label'])],
			$indices
		);
	}

	/** One heading's worth of page. */
	public function renderSection(string $anchor, string $heading, string $body): string {
		return $this->templateEngine->ParseBlock('POSTSTATS_SECTION', [
			'{$ANCHOR}' => htmlspecialchars($anchor),
			'{$HEADING}' => htmlspecialchars($heading),
			'{$BODY}' => $body,
		]);
	}

	/** A standalone message where a chart would otherwise be. */
	public function renderNotice(string $class, string $message): string {
		return $this->templateEngine->ParseBlock('POSTSTATS_NOTICE', [
			'{$CLASS}' => htmlspecialchars($class),
			'{$MESSAGE}' => htmlspecialchars($message),
		]);
	}

	/** The line chart itself, scaled against its own peak. */
	public function renderChart(array $buckets, string $caption): string {
		if (!$buckets) {
			return $this->renderNotice('postStatsEmpty', _T('poststats_empty'));
		}

		$values = array_column($buckets, 'value');
		$peak = max($values);
		$top = $this->plotPoints($values, $peak);
		$partial = $this->isPartial($buckets);

		return $this->templateEngine->ParseBlock('POSTSTATS_CHART', [
			'{$CAPTION}' => htmlspecialchars($caption),
			'{$PEAK}' => number_format($peak),
			'{$MIDPOINT}' => number_format(intdiv($peak, 2)),
			'{$WIDTH}' => self::PLOT_WIDTH,
			'{$HEIGHT}' => self::PLOT_HEIGHT,
			'{$AREA}' => $this->areaPath($top, $this->plotPoints(array_fill(0, count($values), 0), $peak)),
			// An unfinished last bucket is drawn dashed, so its dip does not read as a collapse.
			'{$LINE}' => $this->linePath($partial ? array_slice($top, 0, -1) : $top),
			'{$PARTIAL_LINE}' => $partial ? $this->linePath(array_slice($top, -2)) : '',
			'{$HITS}' => $this->hitAreas($buckets, array_map(fn($bucket) => $this->describeBucket($bucket), $buckets)),
			'{$AXIS}' => $this->axisLabels($buckets),
		]);
	}

	/**
	 * The site-wide chart: one band per board stacked into the total, with a legend.
	 *
	 * @param array $buckets From bucketStack().
	 * @param array $series  From assignSeries().
	 */
	public function renderStackedChart(array $buckets, array $series, string $caption): string {
		if (!$buckets) {
			return $this->renderNotice('postStatsEmpty', _T('poststats_empty'));
		}

		$peak = max(array_column($buckets, 'value'));
		$count = count($buckets);
		$chartId = 'postStatsChart' . ++self::$chartCount;

		// Bottom-up in rank order, so a band holds its place and matches the legend.
		uasort($series, fn($a, $b) => $a['order'] <=> $b['order']);

		$totals = [];
		$bands = [];
		$lines = array_fill(0, $count, []);
		$floor = array_fill(0, $count, 0);

		foreach ($series as $uid => $entry) {
			$values = [];
			foreach ($buckets as $index => $bucket) {
				$value = $bucket['segments'][$uid] ?? 0;
				$values[] = $value;
				if ($value > 0) {
					$lines[$index][] = _T('poststats_segment', $entry['label'], number_format($value));
				}
			}

			$total = array_sum($values);
			if ($total === 0) {
				continue;
			}
			$totals[$uid] = $total;

			$ceiling = array_map(fn($low, $value) => $low + $value, $floor, $values);
			$bands[] = [
				'{$CHART_ID}' => $chartId,
				'{$HUE}' => htmlspecialchars($entry['hue']),
				'{$TIER}' => htmlspecialchars($entry['tier']),
				'{$HATCHED}' => $entry['tier'] !== '0',
				'{$PATH}' => $this->areaPath($this->plotPoints($ceiling, $peak), $this->plotPoints($floor, $peak)),
			];
			$floor = $ceiling;
		}

		$titles = [];
		foreach ($buckets as $index => $bucket) {
			$titles[] = implode("\n", [$this->describeBucket($bucket), ...$lines[$index]]);
		}

		$shade = $this->isPartial($buckets) ? $this->partialSpan($count) : null;

		return $this->templateEngine->ParseBlock('POSTSTATS_STACK', [
			'{$CAPTION}' => htmlspecialchars($caption),
			'{$CHART_ID}' => $chartId,
			'{$PEAK}' => number_format($peak),
			'{$MIDPOINT}' => number_format(intdiv($peak, 2)),
			'{$WIDTH}' => self::PLOT_WIDTH,
			'{$HEIGHT}' => self::PLOT_HEIGHT,
			'{$BANDS}' => $bands,
			'{$PARTIAL}' => $shade !== null,
			'{$PARTIAL_X}' => $shade['x'] ?? '',
			'{$PARTIAL_WIDTH}' => $shade['width'] ?? '',
			'{$HITS}' => $this->hitAreas($buckets, $titles),
			'{$AXIS}' => $this->axisLabels($buckets),
			'{$LEGEND}' => $this->renderLegend($series, $totals),
		]);
	}

	/** Whether the last of several buckets is still filling. */
	private function isPartial(array $buckets): bool {
		return count($buckets) > 1 && !empty($buckets[count($buckets) - 1]['partial']);
	}

	/**
	 * Plot coordinates for a run of values, first and last on the plot's edges.
	 * A single value is drawn flat across the whole width.
	 *
	 * @return array<array{0: float, 1: float}>
	 */
	private function plotPoints(array $values, int $peak): array {
		$values = array_values($values);
		$count = count($values);
		$points = [];

		foreach ($values as $index => $value) {
			$y = self::PLOT_HEIGHT - ($peak > 0 ? $value / $peak * self::PLOT_HEIGHT : 0);
			$points[] = [$count > 1 ? $index * self::PLOT_WIDTH / ($count - 1) : 0, $y];
		}

		if ($count === 1) {
			$points[] = [self::PLOT_WIDTH, $points[0][1]];
		}

		return $points;
	}

	private function linePath(array $points): string {
		$commands = [];
		foreach ($points as $index => [$x, $y]) {
			$commands[] = ($index === 0 ? 'M' : 'L') . $this->coordinate($x) . ' ' . $this->coordinate($y);
		}

		return implode(' ', $commands);
	}

	/** A band between two lines: along the top, then back along the bottom. */
	private function areaPath(array $top, array $bottom): string {
		return $this->linePath(array_merge($top, array_reverse($bottom))) . ' Z';
	}

	/**
	 * One hover strip per bucket, centred on its point, carrying the bucket's description.
	 *
	 * @param string[] $titles One per bucket.
	 */
	private function hitAreas(array $buckets, array $titles): array {
		$count = count($buckets);
		$step = $count > 1 ? self::PLOT_WIDTH / ($count - 1) : self::PLOT_WIDTH;
		$hits = [];

		foreach (array_values($titles) as $index => $title) {
			$centre = $count > 1 ? $index * $step : self::PLOT_WIDTH / 2;
			$left = max(0, $centre - $step / 2);
			$right = min(self::PLOT_WIDTH, $centre + $step / 2);

			$hits[] = [
				'{$X}' => $this->coordinate($left),
				'{$WIDTH}' => $this->coordinate($right - $left),
				'{$HEIGHT}' => self::PLOT_HEIGHT,
				'{$TITLE}' => htmlspecialchars($title),
			];
		}

		return $hits;
	}

	/** The stretch between the last two points, where the unfinished bucket is drawn. */
	private function partialSpan(int $count): array {
		$step = self::PLOT_WIDTH / ($count - 1);

		return ['x' => $this->coordinate(self::PLOT_WIDTH - $step), 'width' => $this->coordinate($step)];
	}

	private function coordinate(float $value): string {
		return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
	}

	/**
	 * The legend. Carries each board's total for the range as well as its swatch — several of
	 * the hues sit under 3:1 against the lighter board themes, and a visible value is what makes
	 * the chart readable without relying on telling those hues apart.
	 */
	private function renderLegend(array $series, array $totals): string {
		$items = [];

		foreach ($series as $uid => $entry) {
			if (empty($totals[$uid])) {
				continue;
			}

			$items[] = [
				'{$HUE}' => htmlspecialchars($entry['hue']),
				'{$TIER}' => htmlspecialchars($entry['tier']),
				'{$LABEL}' => htmlspecialchars($entry['label']),
				'{$URL}' => htmlspecialchars($entry['url']),
				'{$VALUE}' => number_format($totals[$uid]),
			];
		}

		// One series is its own caption; a legend box with a single swatch says nothing.
		if (count($items) < 2) {
			return '';
		}

		return $this->templateEngine->ParseBlock('POSTSTATS_LEGEND', ['{$ITEMS}' => $items]);
	}

	/**
	 * The rate tiles.
	 *
	 * The rate is the one for the span on screen rather than a row of competing averages, so the
	 * zoom links change it and the label says which span it belongs to.
	 *
	 * @param array $series Gap-filled range series, from buildSeries().
	 */
	public function renderTiles(array $stats, array $series, string $rangeLabel, bool $showLastNumber): string {
		$rate = $this->rangeRate($stats, $series);

		$values = [
			'poststats_tile_today' => [null, number_format($stats['todayCount'])],
			'poststats_tile_per_month' => [$rangeLabel, $this->formatRate($rate * self::DAYS_PER_MONTH)],
			'poststats_tile_per_day' => [$rangeLabel, $this->formatRate($rate)],
			'poststats_tile_per_hour' => [$rangeLabel, $this->formatRate($rate / 24)],
			'poststats_tile_total' => [null, number_format($stats['total'])],
			'poststats_tile_first_post' => [null, $stats['firstDay'] === '' ? _T('poststats_none') : $stats['firstDay']],
		];

		if (($stats['undated'] ?? 0) > 0) {
			$values['poststats_tile_undated'] = [null, number_format($stats['undated'])];
		}

		if ($showLastNumber) {
			$values['poststats_tile_latest_no'] = [null, number_format($stats['lastNo'])];
		}

		$tiles = [];
		foreach ($values as $labelKey => [$argument, $value]) {
			$tiles[] = [
				'{$LABEL}' => htmlspecialchars($argument === null ? _T($labelKey) : _T($labelKey, $argument)),
				'{$VALUE}' => htmlspecialchars($value),
			];
		}

		return $this->templateEngine->ParseBlock('POSTSTATS_TILES', ['{$TILES}' => $tiles]);
	}

	/**
	 * Dated posts per day across the span being shown, over the time it has actually covered:
	 * whole days before today plus the part of today that has passed. Floored at an hour so a
	 * span that began moments ago does not read as a flood.
	 */
	private function rangeRate(array $stats, array $series): float {
		if (!$series) {
			return 0.0;
		}

		return array_sum($series) / elapsedDays(count($series) - 1, $stats['secondsToday'] ?? 0);
	}

	/** Per-board breakdown under the site-wide chart. */
	public function renderBoardTable(
		array $boardStats,
		array $boards,
		string $today,
		array $window = [],
		int $windowDays = 30
	): string {
		$rows = [];

		foreach ($boards as $board) {
			$uid = $board->getBoardUID();
			if (!isset($boardStats[$uid])) {
				continue;
			}

			$stats = $boardStats[$uid] + ['today' => $today, 'days' => []];
			$recent = $window[$uid] ?? ['posts' => 0, 'days' => 1];

			$rows[] = [
				// Recent activity, not a lifetime average — which is also what the table is
				// ordered by, so it ranks the boards that are busy now.
				'rate' => $recent['posts'] / $recent['days'],
				'name' => $board->getBoardTitle(),
				'url' => (string)$board->getBoardURL(),
				'todayCount' => $stats['todayCount'],
				'total' => $stats['total'],
				'firstDay' => $stats['firstDay'],
			];
		}

		if (!$rows) {
			return '';
		}

		usort($rows, fn($a, $b) => $b['rate'] <=> $a['rate']);

		$values = [];
		foreach ($rows as $row) {
			$values[] = [
				'{$URL}' => htmlspecialchars($row['url']),
				'{$BOARD}' => htmlspecialchars($row['name']),
				'{$TODAY}' => number_format($row['todayCount']),
				'{$PER_MONTH}' => $this->formatRate($row['rate'] * self::DAYS_PER_MONTH),
				'{$PER_DAY}' => $this->formatRate($row['rate']),
				'{$PER_HOUR}' => $this->formatRate($row['rate'] / 24),
				'{$TOTAL}' => number_format($row['total']),
				'{$FIRST_DAY}' => htmlspecialchars($row['firstDay'] === '' ? _T('poststats_none') : $row['firstDay']),
			];
		}

		return $this->templateEngine->ParseBlock('POSTSTATS_TABLE', [
			'{$ROWS}' => $values,
			'{$CAPTION}' => htmlspecialchars(_T('poststats_table_caption', $windowDays)),
			'{$COL_BOARD}' => htmlspecialchars(_T('poststats_col_board')),
			'{$COL_TODAY}' => htmlspecialchars(_T('poststats_col_today')),
			'{$COL_PER_MONTH}' => htmlspecialchars(_T('poststats_col_per_month')),
			'{$COL_PER_DAY}' => htmlspecialchars(_T('poststats_col_per_day')),
			'{$COL_PER_HOUR}' => htmlspecialchars(_T('poststats_col_per_hour')),
			'{$COL_TOTAL}' => htmlspecialchars(_T('poststats_col_total')),
			'{$COL_FIRST_POST}' => htmlspecialchars(_T('poststats_col_first_post')),
		]);
	}

	/** The zoom links above a chart. */
	public function renderRangeLinks(string $baseUrl, string $currentRange, string $anchor = ''): string {
		$ranges = [];

		foreach (self::RANGES as $key => $range) {
			$ranges[] = [
				'{$URL}' => $baseUrl . '&amp;range=' . $key . ($anchor !== '' ? '#' . htmlspecialchars($anchor) : ''),
				'{$LABEL}' => htmlspecialchars(_T($range['label'])),
				'{$CURRENT}' => $key === $currentRange,
			];
		}

		return $this->templateEngine->ParseBlock('POSTSTATS_RANGES', ['{$RANGES}' => $ranges]);
	}

	private function formatRate(float $rate): string {
		return $rate >= 100 ? number_format($rate, 0) : number_format($rate, 2);
	}

	/** The day, or the range of days, a bucket covers. */
	private function describeSpan(array $bucket): string {
		return $bucket['dayCount'] > 1
			? _T('poststats_bar_span', $bucket['start'], $bucket['end'])
			: $bucket['start'];
	}

	private function describeBucket(array $bucket): string {
		$count = number_format($bucket['value']);

		if ($bucket['dayCount'] > 1) {
			$text = _T(
				'poststats_bar_rate',
				$this->describeSpan($bucket),
				$count,
				$this->formatRate($bucket['value'] / $bucket['dayCount'])
			);
		} else {
			$text = _T('poststats_bar', $bucket['start'], $count);
		}

		return !empty($bucket['partial']) ? _T('poststats_bar_partial', $text) : $text;
	}
}
