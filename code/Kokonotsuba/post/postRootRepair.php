<?php

namespace Kokonotsuba\post;

/**
 * Recovers post times that an auto-updating `root` column overwrote.
 *
 * A `root` declared with ON UPDATE CURRENT_TIMESTAMP moved to the time of every write to its
 * row. The `now` text is never rewritten, so it still holds the original time, shifted by the
 * board's offset. That offset is read off the rows that were left alone.
 */
final class postRootRepair {
	/** A row within this many seconds of its `now` is taken as untouched. */
	public const TOLERANCE = 7200;

	/** Offsets beyond this are not board offsets. */
	private const MAX_OFFSET = 15 * 3600;

	/** Offsets are rounded to this before voting. */
	private const OFFSET_STEP = 900;

	/**
	 * The time in a `now` field, read as if it were UTC.
	 *
	 * @return int|null Unix time, or null when the text holds no date.
	 */
	public static function parseNow(string $now): ?int {
		$pattern = '~(\d{2,4})/(\d{1,2})/(\d{1,2})\D{0,40}?(\d{1,2}):(\d{2})(?::(\d{2}))?~u';
		if (!preg_match($pattern, strip_tags($now), $m)) {
			return null;
		}

		$year = (int)$m[1];
		if ($year < 100) {
			$year += 2000;
		}

		if (!checkdate((int)$m[2], (int)$m[3], $year) || (int)$m[4] > 23) {
			return null;
		}

		return gmmktime((int)$m[4], (int)$m[5], (int)($m[6] ?? 0), (int)$m[2], (int)$m[3], $year);
	}

	/**
	 * Add one row's `root - now` to a vote, if it is close enough to be a board offset.
	 *
	 * @param array<int, int> $votes Offset => rows.
	 */
	public static function vote(array &$votes, int $root, int $parsedNow): void {
		$difference = $root - $parsedNow;
		if (abs($difference) > self::MAX_OFFSET) {
			return;
		}

		$offset = (int)(round($difference / self::OFFSET_STEP) * self::OFFSET_STEP);
		$votes[$offset] = ($votes[$offset] ?? 0) + 1;
	}

	/**
	 * The offset most rows agree on.
	 *
	 * @param array<int, int> $votes Offset => rows.
	 */
	public static function winningOffset(array $votes): ?int {
		if (!$votes) {
			return null;
		}

		arsort($votes);

		return (int)array_key_first($votes);
	}

	/**
	 * Where a row's root belongs, or null when it should be left alone.
	 *
	 * Only ever moves a root earlier: an overwrite can only have moved it later.
	 */
	public static function repairedRoot(int $root, int $parsedNow, int $offset): ?int {
		$original = $parsedNow + $offset;

		return ($original > 0 && $root - $original > self::TOLERANCE) ? $original : null;
	}
}
