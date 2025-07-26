<?php

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

	public function getById(int $id): ?cachedBoardPath {
		$cacheKey = __METHOD__ . ':' . $id;
		return $this->cacheResult($cacheKey, fn() => $this->boardPathRepository->fetchById($id));
	}

	public function getByBoardUid(int $uid): ?cachedBoardPath {
		$cacheKey = __METHOD__ . ':' . $uid;
		return $this->cacheResult($cacheKey, fn() => $this->boardPathRepository->fetchByBoardUid($uid));
	}

	public function getAll(): array {
		return $this->cacheResult(__METHOD__, fn() => $this->boardPathRepository->fetchAll());
	}

	public function updatePath(int $board_uid, string $board_path): void {
		$this->boardPathRepository->updatePathByBoardUid($board_uid, $board_path);
		$this->invalidatePathCache();
	}

	public function addNew(int $board_uid, string $board_path): void {
		$this->boardPathRepository->insertPath($board_uid, $board_path);
		$this->invalidatePathCache();
	}

	public function deleteById(int $id): void {
		$this->boardPathRepository->deleteById($id);
		$this->invalidatePathCache();
	}
}
