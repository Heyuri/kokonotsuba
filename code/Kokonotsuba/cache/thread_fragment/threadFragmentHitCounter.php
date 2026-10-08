<?php

namespace Kokonotsuba\cache\thread_fragment;

/**
 * Hits and misses of the fragment cache for the request in progress, written out once when it ends.
 *
 * Off until something starts it with a sink (the threadCache module, for web requests), so CLI
 * work and the background tasks count nothing. Draws that are not a reader being served, such as
 * the static rebuild, run inside paused().
 */
final class threadFragmentHitCounter {
	/** @var ?\Closure(array): void */
	private static ?\Closure $sink = null;

	private static int $pauseDepth = 0;

	private static bool $shutdownRegistered = false;

	/** @var array<string, array{boardUid: int, threadUid: string, variant: string, hits: int, misses: int}> */
	private static array $counts = [];

	/**
	 * Count from now on, handing the rows to $sink when the request ends. A second call keeps the first sink.
	 *
	 * @param callable(array): void $sink Receives the rows sorted by key, so concurrent writers lock in one order.
	 */
	public static function start(callable $sink, bool $flushOnShutdown = true): void {
		if (self::$sink !== null) {
			return;
		}
		self::$sink = \Closure::fromCallable($sink);

		if ($flushOnShutdown && !self::$shutdownRegistered) {
			self::$shutdownRegistered = true;
			register_shutdown_function([self::class, 'flush']);
		}
	}

	public static function isCounting(): bool {
		return self::$sink !== null && self::$pauseDepth === 0;
	}

	public static function note(int $boardUid, string $threadUid, string $variant, bool $hit): void {
		if (!self::isCounting() || $boardUid <= 0) {
			return;
		}

		$key = $boardUid . "\0" . $threadUid . "\0" . $variant;
		self::$counts[$key] ??= ['boardUid' => $boardUid, 'threadUid' => $threadUid, 'variant' => $variant, 'hits' => 0, 'misses' => 0];
		self::$counts[$key][$hit ? 'hits' : 'misses']++;
	}

	/**
	 * Run $work without counting what it reads.
	 *
	 * @template T
	 * @param callable(): T $work
	 * @return T
	 */
	public static function paused(callable $work): mixed {
		self::$pauseDepth++;
		try {
			return $work();
		} finally {
			self::$pauseDepth--;
		}
	}

	/** Hand what was counted to the sink. A failing sink loses the counts rather than the request. */
	public static function flush(): void {
		if (self::$sink === null || self::$counts === []) {
			return;
		}

		ksort(self::$counts, SORT_STRING);
		$rows = array_values(self::$counts);
		self::$counts = [];

		try {
			(self::$sink)($rows);
		} catch (\Throwable) {
			// statistics only
		}
	}

	/** Back to off, for tests. */
	public static function reset(): void {
		self::$sink = null;
		self::$pauseDepth = 0;
		self::$counts = [];
	}
}
