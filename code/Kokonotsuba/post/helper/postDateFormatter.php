<?php

namespace Kokonotsuba\post\helper;

use DateTime;
use Exception;
use InvalidArgumentException;
use function Kokonotsuba\libraries\_T;

class postDateFormatter {
	public function __construct(
		private string $timeZone
	) {}

	/**
	 * Format a Unix timestamp (as int or string) into HTML
	 */
	public function formatFromTimestamp(int|string $timestamp): string {
		if (is_string($timestamp) && ctype_digit($timestamp)) {
			$timestamp = (int) $timestamp;
		}

		if (!is_int($timestamp)) {
			throw new InvalidArgumentException('Timestamp must be an integer or a numeric string.');
		}

		// Convert to DateTime and reuse formatting logic
		$datetime = (new DateTime())->setTimestamp($timestamp);
		return $this->formatFromDateTime($datetime);
	}

	/**
	 * Format a date string (e.g. "Y-m-d H:i:s") or DateTime object
	 */
	public function formatFromDateString(DateTime|string $datetime): string {
		return $this->formatFromDateTime($this->toDateTime($datetime));
	}

	/**
	 * The same date as plain text, for output that is not HTML — JSON a window inserts as text,
	 * a log line, a mail. Everything rendered into a page wants the marked-up form above.
	 */
	public function formatPlainFromDateString(DateTime|string $datetime): string {
		$parts = $this->splitDate($this->toDateTime($datetime));

		return $parts['date'] . ' (' . $parts['weekday'] . ') ' . $parts['time'];
	}

	private function toDateTime(DateTime|string $datetime): DateTime {
		if (is_string($datetime)) {
			try {
				$datetime = new DateTime($datetime);
			} catch (Exception $e) {
				throw new InvalidArgumentException("Invalid date string: $datetime");
			}
		}

		if (!$datetime instanceof DateTime) {
			throw new InvalidArgumentException("Input must be a DateTime object or date string.");
		}

		return $datetime;
	}

	/**
	 * Core formatting logic shared by both methods
	 */
	private function formatFromDateTime(DateTime $datetime): string {
		$parts = $this->splitDate($datetime);

		return '<span class="postDate">' . $parts['date'] . '</span>'
			. '<span class="postDay">(' . $parts['weekday'] . ')</span>'
			. '<span class="postTime">' . $parts['time'] . '</span>';
	}

	/**
	 * The pieces every format is built from, in board time.
	 *
	 * @return array{date: string, weekday: string, time: string}
	 */
	private function splitDate(DateTime $datetime): array {
		$offsetSeconds = (int) $this->timeZone * 3600;

		// Clone and apply offset
		$adjusted = clone $datetime;
		$adjusted->modify("{$offsetSeconds} seconds");

		$youbi = [_T('sun'), _T('mon'), _T('tue'), _T('wed'), _T('thu'), _T('fri'), _T('sat')];

		return [
			'date' => $adjusted->format('Y/m/d'),
			'weekday' => $youbi[(int) $adjusted->format('w')],
			'time' => $adjusted->format('H:i:s'),
		];
	}
}
