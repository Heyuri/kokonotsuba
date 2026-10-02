<?php

namespace Kokonotsuba\Modules\postStats;

/**
 * Work out which day each post number was handed out on, as far as the evidence allows.
 *
 * Evidence is a set of cuts: a position p in the sequence and a day d, meaning numbers up to p
 * were handed out by the end of d and numbers past p no earlier than d. Every surviving run of
 * consecutive numbers on one day gives a cut either side of it, a counter reading gives one, and
 * the counter itself closes the sequence on today.
 *
 * Survivors count on their own day. A purged stretch between two cuts is dated only when both
 * cuts fall on the same day, otherwise it is undated. Nothing is spread or estimated.
 *
 * A run whose day is earlier than evidence below it (a moved thread keeps its timestamps but
 * takes new numbers) is counted on its day but not trusted as a cut.
 *
 * Everything up to the last cut on or before $through is committed: no later evidence can
 * change it. The rest is pending, recomputed on the next call.
 *
 * @param array       $runs     Lists of ['first' => int, 'last' => int, 'day' => 'Y-m-d'], sorted by first, all past $cutNo.
 * @param array       $readings ['Y-m-d' => counter value].
 * @param int         $cutNo    Where the committed part ends.
 * @param string|null $cutDay   Day of that cut, or null when nothing bounds the start.
 * @param int         $counter  The counter's current value.
 * @param string      $today    Today.
 * @param string      $through  Last completed day.
 * @return array{committed: array, pending: array}
 */
function datePosts(array $runs, array $readings, int $cutNo, ?string $cutDay, int $counter, string $today, string $through): array {
	$cuts = [];

	foreach ($runs as $run) {
		$cuts[] = [$run['first'] - 1, $run['day']];
		$cuts[] = [$run['last'], $run['day']];
	}

	foreach ($readings as $day => $value) {
		if ($value > $cutNo) {
			$cuts[] = [(int)$value, (string)$day];
		}
	}

	$last = $runs ? max(array_column($runs, 'last')) : 0;
	$end = max($counter, $last, $cutNo);
	$cuts[] = [$end, $today];

	usort($cuts, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

	// Only cuts that agree with the evidence below them are kept.
	$kept = [[$cutNo, $cutDay]];
	$lo = $cutDay;
	foreach ($cuts as [$position, $day]) {
		if ($position < $cutNo || ($lo !== null && $day < $lo)) {
			continue;
		}
		$kept[] = [$position, $day];
		$lo = $day;
	}

	// The commit point: the last kept cut on a completed day.
	$commitAt = 0;
	foreach ($kept as $index => [, $day]) {
		if ($day !== null && $day <= $through) {
			$commitAt = $index;
		}
	}
	$commitNo = $kept[$commitAt][0];

	$committed = ['days' => [], 'undated' => 0, 'cutNo' => $commitNo, 'cutDay' => $kept[$commitAt][1]];
	$pending = ['days' => [], 'undated' => 0];

	foreach ($runs as $run) {
		$below = max(0, min($run['last'], $commitNo) - $run['first'] + 1);
		$above = ($run['last'] - $run['first'] + 1) - $below;

		if ($below > 0) {
			$committed['days'][$run['day']] = ($committed['days'][$run['day']] ?? 0) + $below;
		}
		if ($above > 0) {
			$pending['days'][$run['day']] = ($pending['days'][$run['day']] ?? 0) + $above;
		}
	}

	$survivorsUpTo = survivorCounter($runs);
	$survivorsBefore = $survivorsUpTo($cutNo);

	for ($index = 1, $count = count($kept); $index < $count; $index++) {
		[$from, $fromDay] = $kept[$index - 1];
		[$to, $toDay] = $kept[$index];

		$survivors = $survivorsUpTo($to);
		$missing = ($to - $from) - ($survivors - $survivorsBefore);
		$survivorsBefore = $survivors;
		if ($missing <= 0) {
			continue;
		}

		$target = &$committed;
		if ($index > $commitAt) {
			$target = &$pending;
		}

		if ($fromDay !== null && $fromDay === $toDay) {
			$target['days'][$fromDay] = ($target['days'][$fromDay] ?? 0) + $missing;
		} else {
			$target['undated'] += $missing;
		}

		unset($target);
	}

	ksort($committed['days']);
	ksort($pending['days']);

	return ['committed' => $committed, 'pending' => $pending];
}

/**
 * Surviving numbers at or below a position, for positions asked in rising order.
 *
 * @param array $runs Sorted by first, disjoint.
 */
function survivorCounter(array $runs): \Closure {
	$index = 0;
	$whole = 0;

	return function (int $position) use ($runs, &$index, &$whole): int {
		$count = count($runs);

		while ($index < $count && $runs[$index]['last'] <= $position) {
			$whole += $runs[$index]['last'] - $runs[$index]['first'] + 1;
			$index++;
		}

		$partial = ($index < $count && $runs[$index]['first'] <= $position)
			? $position - $runs[$index]['first'] + 1
			: 0;

		return $whole + $partial;
	};
}
