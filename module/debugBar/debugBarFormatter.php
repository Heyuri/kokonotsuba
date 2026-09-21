<?php

namespace Kokonotsuba\Modules\debugBar;

/** Turns a requestMetrics snapshot into the strings the bar shows. Pure, so it is unit tested. */
final class debugBarFormatter {
	/**
	 * @param array{wallSeconds: float, cpuSeconds: float, cpuUserSeconds: float, cpuSystemSeconds: float, queryCount: int, querySeconds: float, peakMemoryBytes: int} $snapshot
	 * @return array{total: string, cpu: string, queries: string, memory: string}
	 */
	public static function format(array $snapshot): array {
		$wall = self::finite($snapshot['wallSeconds'] ?? 0);
		$cpu = self::finite($snapshot['cpuSeconds'] ?? 0);

		return [
			'total' => self::milliseconds($wall),
			'cpu' => self::milliseconds($cpu) . ' (' . self::percent($cpu, $wall) . ')',
			'queries' => (int)($snapshot['queryCount'] ?? 0) . ' / ' . self::milliseconds(self::finite($snapshot['querySeconds'] ?? 0)),
			'memory' => self::megabytes((int)($snapshot['peakMemoryBytes'] ?? 0)),
		];
	}

	public static function milliseconds(float $seconds): string {
		return number_format(self::finite($seconds) * 1000, 1) . ' ms';
	}

	/** CPU as a share of wall time; over 100% means more than one core was busy. */
	public static function percent(float $part, float $whole): string {
		$part = self::finite($part);
		$whole = self::finite($whole);
		if ($whole <= 0) {
			return '0%';
		}

		return number_format(self::finite($part / $whole * 100), 0) . '%';
	}

	public static function megabytes(int $bytes): string {
		return number_format($bytes / 1048576, 1) . ' MB';
	}

	/** A number the bar can print: anything that is not a finite float reads as zero. */
	private static function finite(mixed $value): float {
		$value = is_numeric($value) ? (float)$value : 0.0;

		return is_finite($value) ? $value : 0.0;
	}
}
