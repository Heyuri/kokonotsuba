<?php

namespace Kokonotsuba\cache\path_cache;

/** Service providing a per-request in-memory cache layer over boardPathRepository. */
class boardPathService {
	// In-memory static cache per request
	private static array $pathCache = [];

	public function __construct(
        private readonly boardPathRepository $boardPathRepository
    ) {}

	private function cacheResult(string $key, callable $fn) {
		if (isset(self::$pathCache[$key])) {
			return self::$pathCache[$key];
		}
		return self::$pathCache[$key] = $fn();
	}

	private function invalidatePathCache(): void {
		self::$pathCache = [];
	}

	/**
	 * Retrieve a cached board path by primary key.
	 *
	 * @param int $id Primary key.
	 * @return cachedBoardPath|null
	 */
	public function getById(int $id): ?cachedBoardPath {
		$cacheKey = __METHOD__ . ':' . $id;
		return $this->cacheResult($cacheKey, fn() => $this->boardPathRepository->fetchById($id));
	}

	/**
	 * Retrieve a cached board path by board UID.
	 *
	 * @param int $uid Board UID.
	 * @return cachedBoardPath|null
	 */
	public function getByBoardUid(int $uid): ?cachedBoardPath {
		$cacheKey = __METHOD__ . ':' . $uid;
		return $this->cacheResult($cacheKey, fn() => $this->boardPathRepository->fetchByBoardUid($uid));
	}

	/**
	 * Retrieve all cached board paths.
	 *
	 * @return array Array of cachedBoardPath objects.
	 */
	public function getAll(): array {
		return $this->cacheResult(__METHOD__, fn() => $this->boardPathRepository->fetchAll());
	}

	/**
	 * Update the filesystem path for a board and invalidate the cache.
	 *
	 * @param int    $board_uid  Board UID.
	 * @param string $board_path New filesystem path.
	 * @return void
	 */
	public function updatePath(int $board_uid, string $board_path): void {
		$this->boardPathRepository->updatePathByBoardUid($board_uid, $board_path);
		$this->invalidatePathCache();
	}

	/**
	 * Insert a new board path entry and invalidate the cache.
	 *
	 * @param int    $board_uid  Board UID.
	 * @param string $board_path Filesystem path for the board.
	 * @return void
	 */
	public function addNew(int $board_uid, string $board_path): void {
		$this->boardPathRepository->insertPath($board_uid, $board_path);
		$this->invalidatePathCache();
	}

	/**
	 * Delete a cached path entry by primary key and invalidate the cache.
	 *
	 * @param int $id Primary key of the entry to delete.
	 * @return void
	 */
	public function deleteById(int $id): void {
		$this->boardPathRepository->deleteById($id);
		$this->invalidatePathCache();
	}
}
