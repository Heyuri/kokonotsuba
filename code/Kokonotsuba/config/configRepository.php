<?php

namespace Kokonotsuba\config;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/**
 * Repository for per-board configuration overrides.
 *
 * Each row stores a single board's overrides as a JSON object in the `conf_values` column,
 * keyed by config dot-path (e.g. {"ModuleSettings.RENZOKU3": 15}). Only values that differ
 * from the schema defaults are ever stored here.
 */
class configRepository extends baseRepository {
	/**
	 * @param databaseConnection $databaseConnection Database connection.
	 * @param string             $boardConfigTable   Table name for board config overrides.
	 */
	public function __construct(
		databaseConnection $databaseConnection,
		string $boardConfigTable
	) {
		parent::__construct($databaseConnection, $boardConfigTable);
	}

	/**
	 * Fetch the decoded overrides for a board.
	 *
	 * @param int $boardUid Board UID.
	 * @return array<string, mixed> Decoded dot-path => value overrides (empty if none stored).
	 */
	public function getOverridesByBoardUid(int $boardUid): array {
		$row = $this->findBy('board_uid', $boardUid);

		if (!$row || empty($row['conf_values'])) {
			return [];
		}

		$decoded = json_decode((string)$row['conf_values'], true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Insert or replace the overrides for a board.
	 *
	 * @param int                  $boardUid  Board UID.
	 * @param array<string, mixed> $overrides Dot-path => value overrides to persist.
	 * @return void
	 */
	public function saveOverridesForBoardUid(int $boardUid, array $overrides): void {
		$json = json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($this->exists('board_uid', $boardUid)) {
			$this->updateWhere(['conf_values' => $json], 'board_uid', $boardUid);
		} else {
			$this->insert(['board_uid' => $boardUid, 'conf_values' => $json]);
		}
	}

	/**
	 * Remove the overrides row for a board (reverting it to schema defaults).
	 *
	 * @param int $boardUid Board UID.
	 * @return void
	 */
	public function deleteOverridesForBoardUid(int $boardUid): void {
		$this->deleteWhere('board_uid', $boardUid);
	}
}
