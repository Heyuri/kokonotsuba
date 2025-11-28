<?php

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

		return $this->formatFromDateTime($datetime);
	}

	/**
	 * Core formatting logic shared by both methods
	 */
	private function formatFromDateTime(DateTime $datetime): string {
		$offsetSeconds = $this->timeZone * 3600;

		// Clone and apply offset
		$adjusted = clone $datetime;
		$adjusted->modify("{$offsetSeconds} seconds");

		$youbi = [_T('sun'), _T('mon'), _T('tue'), _T('wed'), _T('thu'), _T('fri'), _T('sat')];
		$weekday = $youbi[(int) $adjusted->format('w')];

		return '<span class="postDate">' . $adjusted->format('Y/m/d') . '</span>'
			. '<span class="postDay">(' . $weekday . ')</span>'
			. '<span class="postTime">' . $adjusted->format('H:i:s') . '</span>';
	}
}
