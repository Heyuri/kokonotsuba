<?php

namespace Kokonotsuba\cache\path_cache;

use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;

/** Repository for the cached board physical-path table. */
class boardPathRepository extends baseRepository {

	public function __construct(
		databaseConnection $databaseConnection,
		string $boardPathTable 
	) {
		parent::__construct($databaseConnection, $boardPathTable);
	}

	/**
	 * Fetch a cached path by its primary key.
	 *
	 * @param int $id Primary key.
	 * @return cachedBoardPath|null Null if not found.
	 */
	public function fetchById(int $id): ?cachedBoardPath {
		return $this->findBy('id', $id, '\Kokonotsuba\cache\path_cache\cachedBoardPath');
	}

	/**
	 * Fetch a cached path by board UID.
	 *
	 * @param int $uid Board UID.
	 * @return cachedBoardPath|null Null if not found.
	 */
	public function fetchByBoardUid(int $uid): ?cachedBoardPath {
		return $this->findBy('boardUID', $uid, '\Kokonotsuba\cache\path_cache\cachedBoardPath');
	}

	/**
	 * Fetch all cached board paths.
	 *
	 * @return array Array of cachedBoardPath objects.
	 */
	public function fetchAll(): array {
		return $this->findAll('', 'ASC', '\Kokonotsuba\cache\path_cache\cachedBoardPath');
	}

	/**
	 * Update the stored path for the specified board.
	 *
	 * @param int    $board_uid  Board UID to update.
	 * @param string $board_path New filesystem path.
	 * @return void
	 */
	public function updatePathByBoardUid(int $board_uid, string $board_path): void {
		$this->updateWhere(['board_path' => $board_path], 'boardUID', $board_uid);
	}

	/**
	 * Insert a new board path entry.
	 *
	 * @param int    $board_uid  Board UID.
	 * @param string $board_path Filesystem path for the board.
	 * @return void
	 */
	public function insertPath(int $board_uid, string $board_path): void {
		$this->insert([
			'boardUID' => $board_uid,
			'board_path' => $board_path,
		]);
	}

	/**
	 * Delete a cached path entry by its primary key.
	 *
	 * @param int $id Primary key of the entry to delete.
	 * @return void
	 */
	public function deleteById(int $id): void {
		$this->deleteWhere('id', $id);
	}
}
