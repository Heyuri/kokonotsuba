<?php

namespace Kokonotsuba\ban;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\ValidatesIdentifiersTrait;

use const Kokonotsuba\GLOBAL_BOARD_UID;

/**
 * Carries the old flat-file bans into the bans table.
 *
 * Bans used to be CSV lines (`ip,start,expires,reason`) in each board's bans.log.txt and in
 * global/globalbans.log. Nothing reads those files any more; the files are left on disk untouched.
 *
 * Imported rows get the default checkpoints, which is what a file ban meant. A line whose start
 * equals its expiry was a warning and comes across as one. Safe to run twice: a ban already in
 * the table with the same pattern, board and filing time is skipped rather than duplicated.
 */
final class legacyBanImporter {
	use ValidatesIdentifiersTrait;

	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly string $banTable,
		private readonly string $boardTable,
		private readonly string $globalDir,
		private readonly string $boardStoragesDir
	) {
		self::validateTableNames($banTable, $boardTable);
	}

	/**
	 * Parse one CSV line into an entry, or null when it is blank or malformed.
	 *
	 * The reason field kept commas as &#44; and newlines as <br />, so it comes back as written.
	 *
	 * @return array{pattern: string, start: int, expires: int, reason: string}|null
	 */
	public static function parseLine(string $line): ?array {
		$line = rtrim($line);

		if ($line === '') {
			return null;
		}

		$parts = explode(',', $line, 4);

		if (count($parts) < 3) {
			return null;
		}

		[$pattern, $start, $expires] = $parts;
		$pattern = trim($pattern);

		if ($pattern === '') {
			return null;
		}

		return [
			'pattern' => $pattern,
			'start' => (int)$start,
			'expires' => (int)$expires,
			'reason' => str_replace('&#44;', ',', $parts[3] ?? ''),
		];
	}

	/**
	 * The ban files that exist on disk: the global one, then one per board with a storage directory.
	 *
	 * @return list<array{label: string, boardUid: int, path: string}>
	 */
	public function sources(): array {
		$sources = [[
			'label' => 'global',
			'boardUid' => GLOBAL_BOARD_UID,
			'path' => rtrim($this->globalDir, '/') . '/globalbans.log',
		]];

		$boards = $this->databaseConnection->fetchAllAsArray(
			"SELECT board_uid, board_title, storage_directory_name FROM {$this->boardTable} WHERE board_uid <> ?",
			[GLOBAL_BOARD_UID]
		);

		foreach ($boards as $board) {
			$directory = (string)$board['storage_directory_name'];

			if ($directory === '') {
				continue;
			}

			$sources[] = [
				'label' => (string)$board['board_title'],
				'boardUid' => (int)$board['board_uid'],
				'path' => rtrim($this->boardStoragesDir, '/') . '/' . $directory . '/bans.log.txt',
			];
		}

		return array_values(array_filter($sources, static fn(array $s): bool => is_file($s['path'])));
	}

	public function hasSources(): bool {
		return $this->sources() !== [];
	}

	/**
	 * @param callable(string):void|null $report Receives one line per file and per skipped/planned entry.
	 * @return array{imported: int, skipped: int, expired: int}
	 */
	public function import(bool $dryRun, bool $includeExpired = false, ?callable $report = null): array {
		$report ??= static function (string $message): void {};

		$defaultCheckpoints = implode(',', array_map(
			fn(banCheckpoint $case): string => $case->value,
			array_filter(banCheckpoint::cases(), fn(banCheckpoint $case): bool => $case->isDefault())
		));

		$now = time();
		$imported = 0;
		$skipped = 0;
		$expired = 0;

		foreach ($this->sources() as $source) {
			$lines = file($source['path']);

			if ($lines === false) {
				$report("could not read {$source['path']}");
				continue;
			}

			$report("{$source['label']}: {$source['path']}");

			foreach ($lines as $line) {
				$entry = self::parseLine($line);

				if ($entry === null) {
					continue;
				}

				$isWarning = $entry['start'] === $entry['expires'];

				if (!$isWarning && !$includeExpired && $entry['expires'] <= $now) {
					$expired++;
					continue;
				}

				$filedAt = date('Y-m-d H:i:s', $entry['start'] ?: $now);

				$alreadyThere = (int)$this->databaseConnection->fetchValue(
					"SELECT COUNT(*) FROM {$this->banTable} WHERE ip_pattern = ? AND board_uid = ? AND filed_at = ?",
					[$entry['pattern'], $source['boardUid'], $filedAt]
				);

				if ($alreadyThere > 0) {
					$skipped++;
					continue;
				}

				if ($dryRun) {
					$report("  [dry-run] {$entry['pattern']}" . ($isWarning ? ' (warning)' : ''));
					$imported++;
					continue;
				}

				$this->databaseConnection->execute(
					"INSERT INTO {$this->banTable}
						(board_uid, ip_pattern, is_wildcard, reason, checkpoints, is_warning, filed_at, expires_at)
					VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
					[
						$source['boardUid'],
						$entry['pattern'],
						str_contains($entry['pattern'], '*') ? 1 : 0,
						$entry['reason'],
						$isWarning ? '' : $defaultCheckpoints,
						$isWarning ? 1 : 0,
						$filedAt,
						$isWarning ? null : date('Y-m-d H:i:s', $entry['expires']),
					]
				);

				$imported++;
			}
		}

		return ['imported' => $imported, 'skipped' => $skipped, 'expired' => $expired];
	}
}
