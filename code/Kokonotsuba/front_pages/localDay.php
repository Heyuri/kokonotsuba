<?php

namespace Kokonotsuba\front_pages;

/** Day boundaries on the instance's clock, the global TIME_ZONE (whole hours from UTC, as postDateFormatter reads it). */
final class localDay {
	/** Unix time of the most recent local midnight at or before $now. */
	public static function startOf(int $now, string $timeZone): int {
		$offset = (int)$timeZone * 3600;

		$local = $now + $offset;

		return $local - (($local % 86400) + 86400) % 86400 - $offset;
	}
}
