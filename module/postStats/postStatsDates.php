<?php

namespace Kokonotsuba\Modules\postStats;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Read a 'Y-m-d' day as a UTC midnight.
 *
 * Day arithmetic is done in UTC throughout: the days themselves are labels handed over by the
 * database, and stepping them in a zone that observes daylight saving can drop or repeat one.
 */
function utcDay(string $day): DateTimeImmutable {
	static $utc = null;
	$utc ??= new DateTimeZone('UTC');

	return new DateTimeImmutable($day, $utc);
}
