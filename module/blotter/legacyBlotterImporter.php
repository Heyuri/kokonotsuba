<?php

namespace Kokonotsuba\Modules\blotter;

use DateTimeImmutable;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\ValidatesIdentifiersTrait;
use RuntimeException;

/**
 * Carries the old blotter flat file into the blotter table.
 *
 * Each line was `date<>comment<>uid`. The file used to be named by ModuleSettings.BLOTTER_FILE,
 * which defaulted to global/blotter.txt; the setting is gone, so that path is where the upgrade
 * looks. Safe to run twice: an entry whose text and date are already in the table is skipped.
 */
final class legacyBlotterImporter {
	use ValidatesIdentifiersTrait;

	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly string $blotterTable
	) {
		self::validateTableName($blotterTable);
	}

	/** Turn a legacy date (slashes or dashes, with or without a time) into DATETIME form. */
	public static function normalizeLegacyDate(string $legacyDate): string {
		$legacyDate = trim($legacyDate);

		foreach (['Y/m/d H:i:s', 'Y-m-d H:i:s', 'Y/m/d', 'Y-m-d'] as $format) {
			$dateTime = DateTimeImmutable::createFromFormat($format, $legacyDate);

			if ($dateTime instanceof DateTimeImmutable) {
				if ($format === 'Y/m/d' || $format === 'Y-m-d') {
					$dateTime = $dateTime->setTime(0, 0, 0);
				}

				return $dateTime->format('Y-m-d H:i:s');
			}
		}

		$dateTime = date_create_immutable($legacyDate);

		if ($dateTime instanceof DateTimeImmutable) {
			return $dateTime->format('Y-m-d H:i:s');
		}

		throw new RuntimeException("Unable to parse blotter date: {$legacyDate}");
	}

	/**
	 * @return list<array{date_added: string, blotter_content: string, legacy_uid: ?string}>
	 */
	public static function parseFile(string $path): array {
		$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if ($lines === false) {
			throw new RuntimeException("Unable to read blotter file: {$path}");
		}

		$entries = [];

		foreach ($lines as $lineNumber => $line) {
			$parts = explode('<>', $line, 3);

			if (count($parts) < 2) {
				throw new RuntimeException('Invalid blotter line format at line ' . ($lineNumber + 1));
			}

			$entries[] = [
				'date_added' => self::normalizeLegacyDate($parts[0]),
				'blotter_content' => trim($parts[1]),
				'legacy_uid' => isset($parts[2]) ? trim($parts[2]) : null,
			];
		}

		return $entries;
	}

	/**
	 * Insert parsed entries. Transactions are the caller's business.
	 *
	 * @param bool $truncate     Empty the table first.
	 * @param bool $skipExisting Leave out entries whose text and date are already there.
	 * @return array{imported: int, skipped: int}
	 */
	public function import(array $entries, bool $dryRun, bool $truncate = false, bool $skipExisting = true): array {
		if (!$dryRun && $truncate) {
			$this->databaseConnection->execute("DELETE FROM {$this->blotterTable}");
		}

		$imported = 0;
		$skipped = 0;

		foreach ($entries as $entry) {
			if ($skipExisting && !$truncate) {
				$present = (int)$this->databaseConnection->fetchValue(
					"SELECT COUNT(*) FROM {$this->blotterTable} WHERE blotter_content = ? AND date_added = ?",
					[$entry['blotter_content'], $entry['date_added']]
				);

				if ($present > 0) {
					$skipped++;
					continue;
				}
			}

			if (!$dryRun) {
				$this->databaseConnection->execute(
					"INSERT INTO {$this->blotterTable} (blotter_content, added_by, date_added) VALUES (?, NULL, ?)",
					[$entry['blotter_content'], $entry['date_added']]
				);
			}

			$imported++;
		}

		return ['imported' => $imported, 'skipped' => $skipped];
	}
}
