<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';

use DateInterval;
use DatePeriod;
use Kokonotsuba\template\templateEngine;

use function Kokonotsuba\libraries\_T;

/**
 * Turns a daily post series into the values the templates under templates/ are filled with.
 *
 * The series is always held per day; anything wider than the bar budget is bucketed into weeks
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

		$period = new DatePeriod(
			utcDay($start),
			new DateInterval('P1D'),
			utcDay($today)->modify('+1 day')
		);

		$series = [];
		foreach ($period as $date) {
			$day = $date->format('Y-m-d');
			$series[$day] = (int)($days[$day] ?? 0);
		}

		return $series;
	}

	/**
	 * Group a daily series down to something that fits the bar budget.
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

	/** How wide a bucket has to be for the series to fit the bar budget. */
	private function bucketUnit(int $dayCount): string {
		if ($dayCount <= $this->maxBars) {
			return 'day';
		}

		return (int)ceil($dayCount / 7) <= $this->maxBars ? 'week' : 'month';
	}

	/** Which bucket a day falls in. Doubles as the bucket's label. */
	private function bucketKey(string $day, string $unit): string {
		return match ($unit) {
			'day' => $day,
			'week' => utcDay($day)->modify('monday this week')->format('Y-m-d'),
			default => substr($day, 0, 7),
		};
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
	 * The labels are laid out with the same spacing as the bars, so taking evenly spaced buckets
	 * keeps each date under the bar it belongs to. Short series get a label per bucket.
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

	/** The bar chart itself, scaled against its own peak. */
	public function renderChart(array $buckets, string $caption): string {
		if (!$buckets) {
			return $this->renderNotice('postStatsEmpty', _T('poststats_empty'));
		}

		$peak = max(array_column($buckets, 'value'));

		$bars = [];
		foreach ($buckets as $bucket) {
			$height = $peak > 0 ? ($bucket['value'] / $peak) * 100 : 0;

			$bars[] = [
				'{$TITLE}' => htmlspecialchars($this->describeBucket($bucket)),
				'{$HEIGHT}' => number_format($height, 2, '.', ''),
				'{$PARTIAL}' => !empty($bucket['partial']),
			];
		}

		return $this->templateEngine->ParseBlock('POSTSTATS_CHART', [
			'{$CAPTION}' => htmlspecialchars($caption),
			'{$PEAK}' => number_format($peak),
			'{$MIDPOINT}' => number_format(intdiv($peak, 2)),
			'{$BARS}' => $bars,
			'{$AXIS}' => $this->axisLabels($buckets),
		]);
	}

	/**
	 * The site-wide chart: the same columns, split into a segment per board, with a legend.
	 *
	 * @param array $buckets From bucketStack().
	 * @param array $series  From assignSeries().
	 */
	public function renderStackedChart(array $buckets, array $series, string $caption): string {
		if (!$buckets) {
			return $this->renderNotice('postStatsEmpty', _T('poststats_empty'));
		}

		$peak = max(array_column($buckets, 'value'));
		$totals = [];
		$columns = [];
		$deepest = 0;

		foreach ($buckets as $bucket) {
			$span = $this->describeSpan($bucket);
			$segments = [];

			foreach ($bucket['segments'] as $uid => $value) {
				if (!isset($series[$uid])) {
					continue;
				}

				// Keyed by the board's rank, so the stack reads the same way in every column and
				// in the legend whichever boards happen to be busy that day.
				$segments[$series[$uid]['order']] = [
					'hue' => $series[$uid]['hue'],
					'tier' => $series[$uid]['tier'],
					'label' => $series[$uid]['label'],
					'value' => $value,
				];

				$totals[$uid] = ($totals[$uid] ?? 0) + $value;
			}

			ksort($segments, SORT_NUMERIC);
			$deepest = max($deepest, count($segments));

			$columns[] = [
				'{$TITLE}' => htmlspecialchars($this->describeBucket($bucket)),
				'{$SEGMENTS}' => $this->buildSegments($segments, $peak, $span),
			];
		}

		return $this->templateEngine->ParseBlock('POSTSTATS_STACK', [
			'{$CAPTION}' => htmlspecialchars($caption),
			'{$PEAK}' => number_format($peak),
			'{$MIDPOINT}' => number_format(intdiv($peak, 2)),
			'{$COLUMNS}' => $columns,
			// The gaps between segments come out of the column's height, so a deep stack gets a
			// finer one - otherwise a site with twenty boards spends more of the plot on gaps
			// than on data.
			'{$GAP}' => $deepest > 8 ? '1px' : '2px',
			'{$AXIS}' => $this->axisLabels($buckets),
			'{$LEGEND}' => $this->renderLegend($series, $totals),
		]);
	}

	/** One column's worth of stacked segments, in the boards' fixed order. */
	private function buildSegments(array $segments, int $peak, string $span): array {
		$values = [];

		foreach ($segments as $segment) {
			$height = $peak > 0 ? ($segment['value'] / $peak) * 100 : 0;

			$values[] = [
				'{$HUE}' => htmlspecialchars($segment['hue']),
				'{$TIER}' => htmlspecialchars($segment['tier']),
				'{$HEIGHT}' => number_format($height, 3, '.', ''),
				'{$TITLE}' => htmlspecialchars(_T(
					'poststats_segment',
					$span,
					$segment['label'],
					number_format($segment['value'])
				)),
			];
		}

		return $values;
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
	 * Posts per day across the span being shown.
	 *
	 * Today is left out of the average because it is still running and would drag it down; if the
	 * span is only today, today is all there is to report.
	 */
	private function rangeRate(array $stats, array $series): float {
		if (!$series) {
			return 0.0;
		}

		$completed = $series;
		unset($completed[$stats['today']]);

		if (!$completed) {
			return (float)$stats['todayCount'];
		}

		return array_sum($completed) / count($completed);
	}

	/** Per-board breakdown under the site-wide chart. */
	public function renderBoardTable(array $boardStats, array $boards, string $today, array $startDays = []): string {
		$rows = [];

		foreach ($boards as $board) {
			$uid = $board->getBoardUID();
			if (!isset($boardStats[$uid])) {
				continue;
			}

			$stats = $boardStats[$uid] + ['today' => $today, 'days' => []];

			$rows[] = [
				'rate' => $this->rateSince($startDays[$uid] ?? $stats['firstDay'], $today, $stats['total']),
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

	/**
	 * Posts per day since a given day, taken from the distance the post-number sequence has moved
	 * rather than from the number of posts still present.
	 *
	 * The caller passes the board's own beginning — the day it was created, not the day its oldest
	 * surviving post happens to carry — so a board that sat quiet for a year before it got going
	 * is not flattered by leaving that year out of the average.
	 */
	private function rateSince(string $startDay, string $today, int $total): float {
		if ($startDay === '' || $total <= 0) {
			return 0.0;
		}

		return $total / max(1, $this->daysBetween($startDay, $today) + 1);
	}

	private function daysBetween(string $from, string $to): int {
		return (int)utcDay($from)->diff(utcDay($to))->days;
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
