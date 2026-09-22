<?php

namespace Kokonotsuba\debug;

/**
 * Cheap per-request counters: wall time, CPU time and the time spent inside database statements.
 *
 * Always on. The two clock reads per statement cost less than the statement, and starting
 * later would miss everything the bootstrap runs before a module can ask for them. Read with
 * snapshot(); the debug bar module is the consumer.
 */
final class requestMetrics {
	private static ?float $startedAt = null;

	/** getrusage() at begin(), so CPU is counted from the request rather than from process start. */
	private static ?array $startUsage = null;

	private static int $queryCount = 0;
	private static float $querySeconds = 0.0;

	/** Statement text is only kept when something will read it: a profile being written. */
	private static bool $keepStatements = false;

	/** @var list<array{sql: string, seconds: float}> */
	private static array $statements = [];

	/** Statements kept per request; a runaway loop must not turn into a runaway profile. */
	public const STATEMENT_LIMIT = 5000;

	/** Longest statement text kept, in bytes. */
	public const STATEMENT_TEXT_LIMIT = 2000;

	/** Mark the start of the request. Called once from the front controller. */
	public static function begin(float $requestTimeFloat): void {
		self::$startedAt = $requestTimeFloat;
		self::$startUsage = function_exists('getrusage') ? (getrusage() ?: null) : null;
		self::$queryCount = 0;
		self::$querySeconds = 0.0;
		self::$statements = [];
	}

	/** Account one database statement; its text is kept only while keepStatements() is on. */
	public static function recordQuery(float $seconds, ?string $sql = null): void {
		self::$queryCount++;
		self::$querySeconds += $seconds;

		if (self::$keepStatements && $sql !== null && count(self::$statements) < self::STATEMENT_LIMIT) {
			self::$statements[] = ['sql' => self::compactSql($sql), 'seconds' => $seconds];
		}
	}

	public static function keepStatements(bool $keep): void {
		self::$keepStatements = $keep;
		if (!$keep) {
			self::$statements = [];
		}
	}

	/** @return list<array{sql: string, seconds: float}> The statements run so far, in order, while kept. */
	public static function statements(): array {
		return self::$statements;
	}

	/** One line of SQL, whitespace collapsed and capped, so the profile stays readable and bounded. */
	public static function compactSql(string $sql): string {
		$compact = trim((string)preg_replace('/\s+/', ' ', $sql));
		if (strlen($compact) > self::STATEMENT_TEXT_LIMIT) {
			$compact = substr($compact, 0, self::STATEMENT_TEXT_LIMIT) . '...';
		}

		return $compact;
	}

	/**
	 * @return array{wallSeconds: float, cpuSeconds: float, cpuUserSeconds: float, cpuSystemSeconds: float, queryCount: int, querySeconds: float, peakMemoryBytes: int}
	 */
	public static function snapshot(?float $now = null): array {
		$now ??= microtime(true);
		$startedAt = self::$startedAt ?? (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? $now);

		[$user, $system] = self::cpuSince(self::$startUsage);

		return [
			'wallSeconds' => max(0.0, $now - $startedAt),
			'cpuSeconds' => $user + $system,
			'cpuUserSeconds' => $user,
			'cpuSystemSeconds' => $system,
			'queryCount' => self::$queryCount,
			'querySeconds' => self::$querySeconds,
			'peakMemoryBytes' => memory_get_peak_usage(true),
		];
	}

	/** @return array{0: float, 1: float} User and system CPU seconds since the given getrusage() reading. */
	private static function cpuSince(?array $start): array {
		if (!function_exists('getrusage')) {
			return [0.0, 0.0];
		}

		$now = getrusage() ?: [];
		$user = self::usageSeconds($now, 'ru_utime') - self::usageSeconds($start ?? [], 'ru_utime');
		$system = self::usageSeconds($now, 'ru_stime') - self::usageSeconds($start ?? [], 'ru_stime');

		return [max(0.0, $user), max(0.0, $system)];
	}

	private static function usageSeconds(array $usage, string $field): float {
		return (float)($usage[$field . '.tv_sec'] ?? 0) + (float)($usage[$field . '.tv_usec'] ?? 0) / 1_000_000;
	}
}
