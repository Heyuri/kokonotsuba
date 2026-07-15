<?php

namespace Koko\Tests\Framework;

use Kokonotsuba\config\configRepository;

/**
 * configRepository backed by an array instead of MariaDB.
 *
 * configService is otherwise pure, so this is all that stands between the config editor's logic
 * and a unit test: it keeps the same interface (and the same JSON round-trip through the stored
 * value, so encoding bugs still surface) without a database.
 */
class InMemoryConfigRepository extends configRepository {
	/** @var array<int, string> board_uid => the stored conf_values JSON. */
	public array $rows = [];

	/** Deliberately does not call parent::__construct(): there is no connection or table. */
	public function __construct() {}

	public function getOverridesByBoardUid(int $boardUid): array {
		if (!isset($this->rows[$boardUid])) {
			return [];
		}

		$decoded = json_decode($this->rows[$boardUid], true);

		return is_array($decoded) ? $decoded : [];
	}

	public function saveOverridesForBoardUid(int $boardUid, array $overrides): void {
		// Store the encoded string, exactly as the real column does, so a value that cannot make
		// the round trip fails here too rather than only in production.
		$json = json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			throw new \RuntimeException('Overrides could not be JSON-encoded: ' . json_last_error_msg());
		}

		$this->rows[$boardUid] = $json;
	}

	public function deleteOverridesForBoardUid(int $boardUid): void {
		unset($this->rows[$boardUid]);
	}

	/** Whether a row exists at all (an empty override set stores no row). */
	public function hasRow(int $boardUid): bool {
		return isset($this->rows[$boardUid]);
	}
}
