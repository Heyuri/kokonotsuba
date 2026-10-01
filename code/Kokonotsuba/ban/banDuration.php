<?php

namespace Kokonotsuba\ban;

use function Kokonotsuba\libraries\_T;

/** Parsing and formatting of the "1y2m3d" duration strings the ban form takes. */
class banDuration {
	private const UNITS = [
		'y' => 31536000,
		'm' => 2597120,
		'w' => 604800,
		'd' => 86400,
		'h' => 3600,
	];

	/**
	 * Seconds described by a duration string. Unrecognised input is worth nothing, which the
	 * ban form treats as a warning.
	 */
	public static function toSeconds(string $duration): int {
		preg_match_all('/(\d+(\.\d+)?)([ymwdh])/', strtolower($duration), $matches, PREG_SET_ORDER);

		$seconds = 0;

		foreach ($matches as $match) {
			$seconds += (float) $match[1] * self::UNITS[$match[3]];
		}

		return (int) $seconds;
	}

	/**
	 * A length written back the way the form takes it, so a ban's own page shows "3d" rather
	 * than "72h". Only the expiry is stored, not what was typed, so this is the plainest string
	 * that reads back to the same length: one unit when one divides it ("10d", "2w"), otherwise
	 * the largest units first ("1y2m", "1d12h"). What is left under a day is rounded to the hour,
	 * the form's smallest unit; a month is not a whole number of hours, so nothing is rounded
	 * before the larger units are taken out.
	 */
	public static function format(int $seconds): string {
		$seconds = max(0, $seconds);

		foreach (['y', 'm', 'w', 'd'] as $unit) {
			if ($seconds > 0 && $seconds % self::UNITS[$unit] === 0) {
				return ($seconds / self::UNITS[$unit]) . $unit;
			}
		}

		$parts = '';

		foreach (['y', 'm', 'w', 'd'] as $unit) {
			if ($seconds >= self::UNITS[$unit]) {
				$parts .= intdiv($seconds, self::UNITS[$unit]) . $unit;
				$seconds %= self::UNITS[$unit];
			}
		}

		$hours = (int) round($seconds / self::UNITS['h']);

		if ($hours > 0 || $parts === '') {
			$parts .= max(1, $hours) . 'h';
		}

		return $parts;
	}

	/**
	 * Whether the string is an explicit zero ("0", "0d"), which the ban form reads as a warning.
	 *
	 * Blank or unparseable input is not the same thing: it means nothing was said about the
	 * length, and the form asks again rather than quietly filing a warning.
	 */
	public static function isExplicitZero(string $duration): bool {
		$duration = trim(strtolower($duration));

		return $duration !== '' && preg_match('/^0+(\\.0+)?[ymwdh]?$/', $duration) === 1;
	}

	/**
	 * Roughly how long is left, for the ban table's "3d left" column. Rounds to the largest unit
	 * that fits, so it reads as an at-a-glance figure rather than a countdown.
	 */
	public static function humanize(int $seconds): string {
		if ($seconds <= 0) {
			return _T('ban_duration_none');
		}

		foreach (['y' => 31536000, 'm' => 2592000, 'd' => 86400, 'h' => 3600, 'i' => 60] as $unit => $size) {
			if ($seconds < $size) {
				continue;
			}

			$value = (int) floor($seconds / $size);

			return match ($unit) {
				'y' => _T('ban_duration_years', $value),
				'm' => _T('ban_duration_months', $value),
				'd' => _T('ban_duration_days', $value),
				'h' => _T('ban_duration_hours', $value),
				'i' => _T('ban_duration_minutes', $value),
			};
		}

		return _T('ban_duration_seconds', $seconds);
	}
}
