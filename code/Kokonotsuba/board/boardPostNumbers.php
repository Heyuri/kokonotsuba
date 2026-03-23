<?php

namespace Kokonotsuba\board;

use Kokonotsuba\database\databaseConnection;

class boardPostNumbers {
	public function __construct(
		private databaseConnection $databaseConnection,
		private readonly string $postNumberTable
	) {}

	public function incrementBoardPostNumber(int $boardUid): int {
		// lock the row for post number
		$this->lockPostNumberRow($boardUid);

		// increment the post number for the board and get the new post number using LAST_INSERT_ID
		$query = "
			INSERT INTO {$this->postNumberTable} (board_uid, post_number)
			VALUES (:board_uid, LAST_INSERT_ID(1))
			ON DUPLICATE KEY UPDATE
				post_number = LAST_INSERT_ID(post_number + 1)
		";
		$params = [':board_uid' => $boardUid];

		$this->databaseConnection->execute($query, $params);

		return (int) $this->databaseConnection->lastInsertId();
	}

	private function lockPostNumberRow(int $boardUid): void {
		// lock the row for the board's post
		$lockRowQuery = "SELECT post_number FROM {$this->postNumberTable} WHERE board_uid = :board_uid FOR UPDATE";
		
		// execute the lock query
		$this->databaseConnection->execute($lockRowQuery, [':board_uid' => $boardUid]);
	}

	public function incrementBoardPostNumberMultiple(int $boardUid, int $count): int {
		if ($count <= 0) {
			return 0;
		}

		$this->lockPostNumberRow($boardUid);

		$query = "
			INSERT INTO {$this->postNumberTable} (board_uid, post_number)
			VALUES (:board_uid, :count)
			ON DUPLICATE KEY UPDATE
				post_number = LAST_INSERT_ID(post_number + VALUES(post_number))
		";

		$params = [
			':board_uid' => $boardUid,
			':count' => $count
		];

		$this->databaseConnection->execute($query, $params);

		return (int) $this->databaseConnection->lastInsertId();
	}

	public function getLastPostNoFromBoard(int $boardUid): int {
		$query = "SELECT post_number FROM {$this->postNumberTable} WHERE board_uid = :board_uid";
		$params = [':board_uid' => $boardUid];

		return (int) $this->databaseConnection->fetchColumn($query, $params);
	}
}