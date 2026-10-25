<?php

namespace Kokonotsuba\post;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\ValidatesIdentifiersTrait;
use PDO;

/**
 * Puts converted posts back to the HTML a pre-conversion backup holds for them.
 *
 * The backup is a copy of the posts table (a dump loaded somewhere reachable by PDO). Only rows
 * it holds as legacy HTML are candidates, so posts made after it, and posts that were already
 * plain text when it was taken, are never touched. Before a row is written, the backup row is
 * run through legacyPostConverter and compared with the live row: a match proves nothing changed
 * since the conversion; a mismatch means the post was edited afterwards and is only restored
 * when forced. Shared by Utilities/post-text-restore-cli.php and its integration test.
 */
final class legacyPostRestorer {
	use ValidatesIdentifiersTrait;

	private const TEXT_COLUMNS = ['com', 'name', 'email', 'sub', 'category'];

	/** @var list<string> The text columns conversion rewrote, less any the backup predates. */
	private array $textColumns;

	/** Whether the backup carries text_format; without it every backup row is legacy. */
	private bool $backupHasFormat;

	public function __construct(
		private readonly databaseConnection $live,
		private readonly PDO $backup,
		private readonly string $backupTable,
		private readonly legacyPostConverter $converter,
		private readonly string $postTable
	) {
		self::validateTableNames($backupTable, $postTable);

		$columns = $backup->query("SHOW COLUMNS FROM `{$backupTable}`")->fetchAll(PDO::FETCH_COLUMN);

		foreach (['post_uid', 'boardUID', 'no', 'com'] as $required) {
			if (!in_array($required, $columns, true)) {
				throw new \RuntimeException("The backup table has no `{$required}` column.");
			}
		}

		$this->textColumns = array_values(array_intersect(self::TEXT_COLUMNS, $columns));
		$this->backupHasFormat = in_array('text_format', $columns, true);
	}

	/** @return list<string> */
	public function textColumns(): array {
		return $this->textColumns;
	}

	/**
	 * Walk the backup against the live table.
	 *
	 * $onRow gets the backup row, the live row and whether the live text is exactly what
	 * converting the backup row produces. Backup rows with no converted live row are skipped.
	 *
	 * @param callable(array, array, bool):void $onRow
	 * @return int Backup rows walked.
	 */
	public function walk(callable $onRow, ?int $boardUid = null, ?int $limit = null, int $batchSize = 500): int {
		$where = ['post_uid > ?'];
		$params = [];

		if ($this->backupHasFormat) {
			$where[] = 'text_format = ' . textFormat::LEGACY_HTML->value;
		}

		if ($boardUid !== null) {
			$where[] = 'boardUID = ?';
			$params[] = $boardUid;
		}

		$columns = implode(', ', $this->textColumns);
		$backupSelect = "SELECT post_uid, boardUID, no, {$columns} FROM `{$this->backupTable}`"
			. ' WHERE ' . implode(' AND ', $where) . ' ORDER BY post_uid LIMIT ';
		$liveSelect = "SELECT post_uid, boardUID, no, text_format, {$columns} FROM {$this->postTable}"
			. ' WHERE text_format <> ' . textFormat::LEGACY_HTML->value . ' AND post_uid IN (';

		$batchSize = max(1, $batchSize);
		$after = 0;
		$walked = 0;

		while ($limit === null || $walked < $limit) {
			$size = $limit === null ? $batchSize : min($batchSize, $limit - $walked);
			$statement = $this->backup->prepare($backupSelect . $size);
			$statement->execute([$after, ...$params]);
			$backupRows = $statement->fetchAll(PDO::FETCH_ASSOC);

			if (!$backupRows) {
				break;
			}

			$walked += count($backupRows);
			$after = (int)end($backupRows)['post_uid'];

			$uids = array_column($backupRows, 'post_uid');
			$live = [];

			foreach ($this->live->fetchAllAsArray($liveSelect . implode(',', array_fill(0, count($uids), '?')) . ')', $uids) as $row) {
				$live[(int)$row['post_uid']] = $row;
			}

			foreach ($backupRows as $backupRow) {
				$liveRow = $live[(int)$backupRow['post_uid']] ?? null;

				if ($liveRow !== null) {
					$onRow($backupRow, $liveRow, $this->isUnchangedSinceConversion($backupRow, $liveRow));
				}
			}
		}

		return $walked;
	}

	/**
	 * Restorable and edited counts per board.
	 *
	 * @return array<int, array{restorable: int, edited: int}>
	 */
	public function summary(?int $boardUid = null, ?int $limit = null, int $batchSize = 500): array {
		$perBoard = [];

		$this->walk(static function (array $backupRow, array $liveRow, bool $unchanged) use (&$perBoard): void {
			$board = (int)$liveRow['boardUID'];
			$perBoard[$board] ??= ['restorable' => 0, 'edited' => 0];
			$perBoard[$board][$unchanged ? 'restorable' : 'edited']++;
		}, $boardUid, $limit, $batchSize);

		ksort($perBoard);

		return $perBoard;
	}

	/**
	 * Copy the backup's text back and flag the rows legacy HTML.
	 *
	 * @param callable(int):void|null $progress Receives the running restored count.
	 * @return array{restored: int, skipped: list<array>, boards: list<int>} Rows restored, live rows
	 *         skipped as edited since conversion, and the boards touched.
	 */
	public function restore(bool $dryRun, bool $force = false, ?int $boardUid = null, ?int $limit = null, int $batchSize = 500, ?callable $progress = null): array {
		$assignments = implode(', ', array_map(fn(string $c): string => "{$c} = ?", $this->textColumns));
		$update = "UPDATE {$this->postTable} SET {$assignments}, text_format = " . textFormat::LEGACY_HTML->value
			. ' WHERE post_uid = ? AND text_format <> ' . textFormat::LEGACY_HTML->value;

		$restored = 0;
		$skipped = [];
		$boards = [];

		$this->walk(function (array $backupRow, array $liveRow, bool $unchanged) use (&$restored, &$skipped, &$boards, $update, $dryRun, $force, $progress): void {
			if (!$unchanged && !$force) {
				$skipped[] = $liveRow;
				return;
			}

			if (!$dryRun) {
				$values = array_map(fn(string $c): string => (string)($backupRow[$c] ?? ''), $this->textColumns);
				$this->live->execute($update, [...$values, (int)$liveRow['post_uid']]);
			}

			$restored++;
			$boards[(int)$liveRow['boardUID']] = true;

			if ($progress !== null) {
				$progress($restored);
			}
		}, $boardUid, $limit, $batchSize);

		return ['restored' => $restored, 'skipped' => $skipped, 'boards' => array_keys($boards)];
	}

	/** Whether the live row is exactly what converting the backup row gives, so nothing edited it since. */
	private function isUnchangedSinceConversion(array $backupRow, array $liveRow): bool {
		$expected = array_merge($backupRow, $this->converter->convertRow($backupRow + ['category' => '']));

		foreach ($this->textColumns as $column) {
			if ((string)($expected[$column] ?? '') !== (string)($liveRow[$column] ?? '')) {
				return false;
			}
		}

		return true;
	}
}
