<?php

class boardPathRepository {

	public function __construct(
		private DatabaseConnection $databaseConnection,
		private readonly string $boardPathTable 
	) {}

	public function fetchById(int $id): ?cachedBoardPath {
		$query = "SELECT * FROM {$this->boardPathTable} WHERE id = ?";
		return $this->databaseConnection->fetchAsClass($query, [$id], 'cachedBoardPath') ?: null;
	}

	public function fetchByBoardUid(int $uid): ?cachedBoardPath {
		$query = "SELECT * FROM {$this->boardPathTable} WHERE boardUID = ?";
		return $this->databaseConnection->fetchAsClass($query, [$uid], 'cachedBoardPath') ?: null;
	}

	public function fetchAll(): array {
		$query = "SELECT * FROM {$this->boardPathTable}";
		return $this->databaseConnection->fetchAllAsClass($query, [], 'cachedBoardPath');
	}

	public function updatePathByBoardUid(int $board_uid, string $board_path): void {
		$query = "UPDATE {$this->boardPathTable} SET board_path = :board_path WHERE boardUID = :boardUID";
		$params = [
			':board_path' => $board_path,
			':boardUID' => $board_uid,
		];
		$this->databaseConnection->execute($query, $params);
	}

	public function insertPath(int $board_uid, string $board_path): void {
		$query = "INSERT INTO {$this->boardPathTable} (boardUID, board_path) VALUES(:boardUID, :board_path)";
		$params = [
			':boardUID' => $board_uid,
			':board_path' => $board_path,
		];
		$this->databaseConnection->execute($query, $params);
	}

	public function deleteById(int $id): void {
		$query = "DELETE FROM {$this->boardPathTable} WHERE id = ?";
		$this->databaseConnection->execute($query, [$id]);
	}
}
