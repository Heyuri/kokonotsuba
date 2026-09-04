<?php

namespace Kokonotsuba\post;

use Kokonotsuba\config\configService;
use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\ValidatesIdentifiersTrait;

/**
 * Walks the posts table converting rows stored as HTML into plain text.
 *
 * legacyTextConverter does the unwinding of one value; this finds the rows, decides which
 * columns changed, and flips text_format. It only ever touches LEGACY_HTML rows, so a run that
 * stops part way (or a --limit) picks up where it left off. Shared by the migration that runs
 * it on upgrade and by Utilities/post-text-format-cli.php, which adds preview and per-board runs.
 */
final class legacyPostConverter {
	use ValidatesIdentifiersTrait;

	private const COLUMNS = 'post_uid, boardUID, no, name, email, sub, com, category';

	/** @var array<int, array> FORTUNES per board, so a drawn fortune converts back to its index. */
	private array $fortunes = [];

	public function __construct(
		private readonly databaseConnection $databaseConnection,
		private readonly configService $configService,
		private readonly string $postTable,
		private readonly string $boardTable
	) {
		self::validateTableNames($postTable, $boardTable);
	}

	/** Posts still stored as HTML. */
	public function countLegacy(?int $boardUid = null): int {
		[$where, $params] = $this->scope(textFormat::LEGACY_HTML->value, $boardUid);

		return (int)$this->databaseConnection->fetchValue(
			"SELECT COUNT(*) FROM {$this->postTable} WHERE {$where}",
			$params
		);
	}

	/**
	 * Post counts per board and format.
	 *
	 * @return list<array{boardUID: int|string, board_title: ?string, text_format: int|string, total: int|string}>
	 */
	public function countsByBoard(?int $boardUid = null): array {
		$where = $boardUid !== null ? 'WHERE p.boardUID = ?' : '';
		$params = $boardUid !== null ? [$boardUid] : [];

		return $this->databaseConnection->fetchAllAsArray("
			SELECT p.boardUID, b.board_title, p.text_format, COUNT(*) AS total
			FROM {$this->postTable} p
			LEFT JOIN {$this->boardTable} b ON b.board_uid = p.boardUID
			{$where}
			GROUP BY p.boardUID, b.board_title, p.text_format
			ORDER BY p.boardUID, p.text_format
		", $params);
	}

	/** The newest legacy rows, for previewing what conversion would do. */
	public function sample(int $count, ?int $boardUid = null): array {
		[$where, $params] = $this->scope(textFormat::LEGACY_HTML->value, $boardUid);
		$count = max(1, $count);

		return $this->databaseConnection->fetchAllAsArray(
			"SELECT " . self::COLUMNS . " FROM {$this->postTable} WHERE {$where} ORDER BY post_uid DESC LIMIT {$count}",
			$params
		);
	}

	/**
	 * Convert one row, returning only the columns that actually changed.
	 *
	 * @return array<string, string>
	 */
	public function convertRow(array $row): array {
		$fortunes = $this->fortunesForBoard((int)$row['boardUID']);

		$converted = [
			'com' => legacyTextConverter::comment((string)$row['com'], $fortunes),
			'name' => legacyTextConverter::field((string)$row['name']),
			'email' => legacyTextConverter::field((string)$row['email']),
			'sub' => legacyTextConverter::field((string)$row['sub']),
			'category' => legacyTextConverter::field((string)($row['category'] ?? '')),
		];

		return array_filter(
			$converted,
			fn(string $value, string $column): bool => $value !== (string)($row[$column] ?? ''),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Convert legacy posts and mark them plain text.
	 *
	 * @param callable(int, int):void|null $progress Receives (done, target) after each batch.
	 * @return array{converted: int, rewritten: int} Posts flagged, and how many had markup to unwind.
	 */
	public function convert(bool $dryRun, ?int $boardUid = null, ?int $limit = null, int $batchSize = 500, ?callable $progress = null): array {
		[$where, $params] = $this->scope(textFormat::LEGACY_HTML->value, $boardUid);

		$remaining = $this->countLegacy($boardUid);
		$target = $limit !== null ? min($limit, $remaining) : $remaining;
		$batchSize = max(1, $batchSize);

		$converted = 0;
		$rewritten = 0;
		// Walk forwards by post_uid rather than paging with OFFSET: conversion changes which rows
		// match the WHERE clause, so an offset would skip rows as the set shrinks under it.
		$after = 0;

		while ($converted < $target) {
			$size = min($batchSize, $target - $converted);

			$rows = $this->databaseConnection->fetchAllAsArray(
				"SELECT " . self::COLUMNS . " FROM {$this->postTable}
				 WHERE {$where} AND post_uid > ? ORDER BY post_uid LIMIT {$size}",
				[...$params, $after]
			);

			if (!$rows) {
				break;
			}

			foreach ($rows as $row) {
				$after = (int)$row['post_uid'];
				$changes = $this->convertRow($row);
				$converted++;

				if ($changes) {
					$rewritten++;
				}

				if ($dryRun) {
					continue;
				}

				// The flag goes on every row, changed or not: a comment that needed no rewriting is
				// still plain text, and leaving it legacy would keep it out of render-time formatting.
				$changes['text_format'] = textFormat::PLAIN_TEXT->value;

				$assignments = implode(', ', array_map(fn(string $c): string => "{$c} = ?", array_keys($changes)));

				$this->databaseConnection->execute(
					"UPDATE {$this->postTable} SET {$assignments} WHERE post_uid = ?",
					[...array_values($changes), $after]
				);
			}

			if ($progress !== null) {
				$progress($converted, $target);
			}
		}

		return ['converted' => $converted, 'rewritten' => $rewritten];
	}

	/** @return array{0: string, 1: array} WHERE clause and its parameters. */
	private function scope(int $format, ?int $boardUid): array {
		$where = 'text_format = ?';
		$params = [$format];

		if ($boardUid !== null) {
			$where .= ' AND boardUID = ?';
			$params[] = $boardUid;
		}

		return [$where, $params];
	}

	private function fortunesForBoard(int $boardUid): array {
		if (!array_key_exists($boardUid, $this->fortunes)) {
			$value = $this->configService->getEffectiveValues($boardUid)['FORTUNES'] ?? [];
			$this->fortunes[$boardUid] = is_array($value) ? $value : [];
		}

		return $this->fortunes[$boardUid];
	}
}
