<?php

namespace Kokonotsuba\board;

use Exception;
use Kokonotsuba\database\baseRepository;
use Kokonotsuba\database\databaseConnection;
use function Kokonotsuba\libraries\pdoPlaceholdersForIn;

/** Repository for board records, with a per-request static result cache. */
class boardRepository extends baseRepository {
	// Store results to avoid over-querying within a request
	private static array $boardResultCache = [];

	/**
	 * @param databaseConnection $databaseConnection Database connection.
	 * @param string             $boardTable          Table name for boards.
	 */
	public function __construct(
		databaseConnection $databaseConnection,
		string $boardTable
	) {
		parent::__construct($databaseConnection, $boardTable);
	}

	/**
	 * Return a cached result or compute and cache it via $fn.
	 *
	 * @param string   $key Cache key string.
	 * @param callable $fn  Callable that returns the value to cache.
	 * @return mixed Cached or freshly computed result.
	 */
	private function cacheMethodResult(string $key, callable $fn) {
		if (isset(self::$boardResultCache[$key])) {
			return self::$boardResultCache[$key];
		}
		return self::$boardResultCache[$key] = $fn();
	}

	/**
	 * Clear the per-request board result cache.
	 *
	 * @return void
	 */
	private function invalidateBoardCache(): void {
		self::$boardResultCache = [];
	}

	/**
	 * Fetch a single board by its UID (result cached per request).
	 *
	 * @param int|string $uid Board UID.
	 * @return boardData|null Hydrated boardData object, or null if not found.
	 */
	public function getBoardByUID($uid) {
		$cacheKey = __METHOD__ . ':' . intval($uid);

		return $this->cacheMethodResult($cacheKey, function () use ($uid) {
			return $this->findBy('board_uid', $uid, '\Kokonotsuba\board\boardData');
		});
	}


	/**
	 * Delete a board row by its UID and invalidate the result cache.
	 *
	 * @param int|string $uid Board UID to delete.
	 * @return void
	 */
	public function deleteBoardByUID($uid) {
		$this->deleteWhere('board_uid', $uid);
		$this->invalidateBoardCache();
	}

	/**
	 * Fetch all boards ordered by UID (result cached per request).
	 *
	 * @return boardData[] Array of hydrated boardData objects.
	 */
	public function getAllBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			return $this->findAll('', 'ASC', '\Kokonotsuba\board\boardData');
		});
	}

	/**
	 * Fetch all boards with UID > 0 (result cached per request).
	 *
	 * @return boardData[] Array of hydrated boardData objects.
	 */
	public function getAllRegularBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT * FROM {$this->table} WHERE board_uid > 0";
			return $this->queryAllAsClass($query, [], '\Kokonotsuba\board\boardData');
		});
	}

	/**
	 * Fetch board_uid of every regular board (UID > 0), result cached per request.
	 *
	 * @return boardData[] Partially-hydrated boardData objects (board_uid only).
	 */
	public function getAllRegularBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->table} WHERE board_uid > 0";
			return $this->queryAllAsClass($query, [], '\Kokonotsuba\board\boardData');
		});
	}

	/**
	 * Fetch a flat array of all board UIDs (result cached per request).
	 *
	 * @return int[] Array of board_uid integers.
	 */
	public function getAllBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->table}";
			$boards = $this->queryAllAsIndexArray($query);
			return array_merge(...$boards);
		});
	}

	/**
	 * Fetch all boards where listed = true (result cached per request).
	 *
	 * @return boardData[] Array of hydrated boardData objects.
	 */
	public function getAllListedBoards() {
		return $this->cacheMethodResult(__METHOD__, function () {
			return $this->findAllBy('listed', true, '\Kokonotsuba\board\boardData');
		});
	}

	/**
	 * Fetch the UIDs of all listed boards as a flat array (result cached per request).
	 *
	 * @return int[] Array of board_uid integers.
	 */
	public function getAllListedBoardUIDs() {
		return $this->cacheMethodResult(__METHOD__, function () {
			$query = "SELECT board_uid FROM {$this->table} WHERE listed = true";
			$boards = $this->queryAllAsIndexArray($query);
			return array_merge(...$boards);
		});
	}

	/**
	 * Fetch board objects for the given array of UIDs (result cached per UID combination).
	 *
	 * @param int[] $uidList Array of board UIDs.
	 * @return boardData[] Array of hydrated boardData objects.
	 */
	public function getBoardsFromUIDs(array $uidList): array {
		// Create a cache key based on the method and UID list
		$cacheKey = __METHOD__ . ':' . implode(',', $uidList);
	
		return $this->cacheMethodResult($cacheKey, function () use ($uidList) {
			$inClause = pdoPlaceholdersForIn($uidList);
			$query = "SELECT * FROM {$this->table} WHERE board_uid IN $inClause";
			$uidList = array_values($uidList);
			return $this->queryAllAsClass($query, $uidList, '\Kokonotsuba\board\boardData');
		});
	}
	
	

	/**
	 * Insert a new board row and invalidate the result cache.
	 *
	 * @param string $board_identifier        Board short identifier (slug).
	 * @param string $board_title             Board display title.
	 * @param string $board_sub_title         Board subtitle.
	 * @param int    $listed                  Whether the board appears in board listings (0 or 1).
	 * @param string $config_name             Config file name for the board.
	 * @param string $storage_directory_name  Directory name for board storage.
	 * @return void
	 */
	public function addNewBoard($board_identifier, $board_title, $board_sub_title, $listed, $config_name, $storage_directory_name) {
		$this->insert([
			'board_identifier' => $board_identifier,
			'board_title' => $board_title,
			'board_sub_title' => $board_sub_title,
			'listed' => $listed,
			'config_name' => $config_name,
			'storage_directory_name' => $storage_directory_name,
		]);
		$this->invalidateBoardCache();
	}

	/**
	 * Update specified columns on a board row and invalidate the result cache.
	 *
	 * @param int   $boardUID Board UID to update.
	 * @param array $fields   Associative array of column => value pairs.
	 * @return void
	 * @throws Exception If $fields is empty.
	 */
	public function updateBoardByUID(int $boardUID, array $fields): void {
		if (empty($fields)) {
			throw new Exception("No valid fields provided to update.");
		}
		$this->updateWhere($fields, 'board_uid', $boardUID);
		$this->invalidateBoardCache();
	}


	/**
	 * Return the next AUTO_INCREMENT value for the boards table (result cached per request).
	 *
	 * @return int Next available board UID.
	 */
	public function getNextBoardUID() {
		return $this->cacheMethodResult(__METHOD__, function () {
			return $this->getNextAutoIncrement();
		});
	}

	/**
	 * Return the highest board UID currently in the table (result cached per request).
	 *
	 * @return int|null Maximum board UID, or null if the table is empty.
	 */
	public function getLastBoardUID() {
		return $this->cacheMethodResult(__METHOD__, function () {
			return $this->queryColumn("SELECT MAX(board_uid) FROM {$this->table}");
		});
	}  
}