<?php

namespace Kokonotsuba\Modules\postStats;

/**
 * Counter readings for the days before readings were recorded, from the surviving posts.
 *
 * A day's reading is its highest surviving number. It is only kept when every later day's posts
 * are numbered above it and it does not pass the first real reading: a moved thread keeps its
 * old timestamps but takes new numbers, and would otherwise date everything below it.
 *
 * @param array    $days    ['Y-m-d' => ['min' => int, 'max' => int]] for every day with posts, oldest first.
 * @param string   $before  Only days before this one get a reading.
 * @param int|null $ceiling The first recorded reading, which no backfilled one may pass.
 * @return array ['Y-m-d' => post number], oldest first.
 */
function backfillReadings(array $days, string $before, ?int $ceiling = null): array {
	$readings = [];
	$floor = $ceiling === null ? PHP_INT_MAX : $ceiling + 1;

	foreach (array_reverse($days, true) as $day => $range) {
		if ((string)$day < $before && $range['max'] < $floor) {
			$readings[(string)$day] = (int)$range['max'];
		}
		$floor = min($floor, (int)$range['min']);
	}

	return array_reverse($readings, true);
}
