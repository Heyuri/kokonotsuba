<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsDates.php';

/**
 * Turns known readings of a board's post counter into a count for every day.
 *
 * A reading is a day where the counter's value is known: the board's creation (nothing handed out
 * yet), a day that still has posts on it (its highest surviving number), a recorded counter
 * reading, or today (the counter itself). Between two readings the difference is known but the
 * days are not — the posts that would have said which day they belonged to have been pruned — so
 * it is spread evenly across the days it must have fallen in.
 *
 * That is an estimate, and deliberately a flat one: with the rows gone there is nothing left to
 * shape it with. What it is not is the alternative, which is to drop the whole backlog onto
 * whichever day still happens to have the oldest surviving post — a board that has made half a
 * million posts and keeps ninety days of them reads as half a million posts in one day.
 *
 * Totals are exact either way. Only the attribution is estimated, and every number is placed
 * exactly once: the remainder of an uneven division is handed out a day at a time rather than
 * rounded away.
 *
 * @param array $readings [day => counter value], in any order. Fewer than two means nothing can
 *                        be worked out and the result is empty.
 * @return array [day => posts made], oldest first, covering every day between the readings.
 */
function spreadReadings(array $readings): array {
	if (count($readings) < 2) {
		return [];
	}

	ksort($readings);

	$days = [];
	$previousDay = null;
	$previousValue = 0;

	foreach ($readings as $day => $value) {
		$day = (string)$day;
		$value = (int)$value;

		if ($previousDay === null) {
			$previousDay = $day;
			$previousValue = $value;
			continue;
		}

		// A reading can only ever be a lower bound — posts made after it may since have gone —
		// so the sequence is never allowed to run backwards.
		$value = max($value, $previousValue);

		// Stepped as whole days of seconds rather than by rebuilding a date object each time:
		// a decade of history across a site's worth of boards is a few hundred thousand steps,
		// and gmdate() is a fraction of the cost of DateTimeImmutable::modify().
		$from = utcDay($previousDay)->getTimestamp();
		$to = utcDay($day)->getTimestamp();
		$span = intdiv($to - $from, 86400);
		$made = $value - $previousValue;

		if ($span <= 0) {
			$previousValue = $value;
			continue;
		}

		$each = intdiv($made, $span);
		$remainder = $made % $span;

		for ($step = 1; $step <= $span; $step++) {
			// The odd posts left over by the division go on the days closest to the reading that
			// closed the gap, which is where the evidence is.
			$extra = $step > ($span - $remainder) ? 1 : 0;
			$days[gmdate('Y-m-d', $from + $step * 86400)] = $each + $extra;
		}

		$previousDay = $day;
		$previousValue = $value;
	}

	return $days;
}
