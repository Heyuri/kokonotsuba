<?php

class boardPostNumbers {
	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $postNumberTable
	) {}

	public function incrementBoardPostNumber(int $boardUid): void {
		$query = "
			INSERT INTO {$this->postNumberTable} (board_uid, post_number)
			VALUES (:board_uid, 1)
			ON DUPLICATE KEY UPDATE post_number = post_number + 1
		";
		$params = [':board_uid' => $boardUid];

		$this->databaseConnection->execute($query, $params);
	}

	public function incrementBoardPostNumberMultiple(int $boardUid, int $count): void {
		if ($count <= 0) {
			return;
		}

		$query = "
			INSERT INTO {$this->postNumberTable} (board_uid, post_number)
			VALUES (:board_uid, :count)
			ON DUPLICATE KEY UPDATE post_number = post_number + VALUES(post_number)
		";
		$params = [
			':board_uid' => $boardUid,
			':count' => $count
		];

		$this->databaseConnection->execute($query, $params);
	}

	public function getLastPostNoFromBoard(int $boardUid): int {
		$query = "SELECT post_number FROM {$this->postNumberTable} WHERE board_uid = :board_uid";
		$params = [':board_uid' => $boardUid];

		return (int) $this->databaseConnection->fetchColumn($query, $params);
	}
}