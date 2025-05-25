<?php
/**
 * PIO Condition Object
 *
 * Check if a post meets deletion conditions and return IDs to be deleted
 *
 * @package PMCLibrary
 * @version $Id$
 */


/**
 * Post count-based pruning condition
 * Triggers when post count exceeds threshold
 */
class ByPostCountCondition implements IPIOCondition {
	public static function check($board, $type, $limit): bool {
		$PIO = PIOPDO::getInstance();
		$adjusted = $limit * ($type === 'predict' ? 0.95 : 1);
		return $PIO->postCountFromBoard($board) >= $adjusted;
	}

	public static function listee($board, $type, $limit): array {
		$PIO = PIOPDO::getInstance();
		$adjusted = intval($limit * ($type === 'predict' ? 0.95 : 1));
		return $PIO->fetchPostListFromBoard($board, 0, $adjusted - 1, $limit);
	}

	public static function info($board, $limit): string {
		$PIO = PIOPDO::getInstance();
		$pcnt = $PIO->postCountFromBoard($board);
		return __CLASS__ . ": $pcnt/$limit (" . sprintf("%.2f%%", ($pcnt / $limit * 100)) . ")";
	}
}

/**
 * Thread count-based pruning condition
 * Triggers when thread count exceeds threshold
 */
class ByThreadCountCondition implements IPIOCondition {
	public static function check($board, $type, $limit): bool {
		$threadSingleton = threadSingleton::getInstance();
		$adjusted = $limit * ($type === 'predict' ? 0.95 : 1);
		return $threadSingleton->threadCountFromBoard($board) >= $adjusted;
	}

	public static function listee($board, $type, $limit): array {
		$threadSingleton = threadSingleton::getInstance();
		$adjusted = intval(($limit - 1) * ($type === 'predict' ? 0.95 : 1));
		return $threadSingleton->fetchThreadListFromBoard($board, $adjusted, $limit, true);
	}

	public static function info($board, $limit): string {
		$threadSingleton = threadSingleton::getInstance();
		$tcnt = $threadSingleton->threadCountFromBoard($board);
		return __CLASS__ . ": $tcnt/$limit (" . sprintf("%.2f%%", ($tcnt / $limit * 100)) . ")";
	}
}


/**
 * Thread lifetime-based pruning condition
 * Triggers when oldest thread exceeds age threshold in days
 */
class ByThreadAliveTimeCondition implements IPIOCondition {
	public static function check($board, $type, $limit): bool {
		$PIO = PIOPDO::getInstance();
		$threadSingleton = threadSingleton::getInstance();

		$totalThreads = $threadSingleton->threadCountFromBoard($board);
		if ($totalThreads === 0) return false;

		// Get the oldest thread number
		$oldestThreadNo = $threadSingleton->fetchThreadListFromBoard($board, $totalThreads - 1, 1, true);
		$oldestThread = $PIO->fetchPosts($oldestThreadNo[0] ?? null);

		if (empty($oldestThread)) return false;

		$oldestTime = (int)substr($oldestThread[0]['tim'], 0, 10);
		$threshold = 86400 * $limit * ($type === 'predict' ? 0.95 : 1);

		return (time() - $oldestTime) >= $threshold;
	}

	public static function listee($board, $type, $limit): array {
		$PIO = PIOPDO::getInstance();
		$threadSingleton = threadSingleton::getInstance();

		// Get thread number array (oldest to newest)
		$threads = $threadSingleton->fetchThreadListFromBoard($board, 0, 0, true);
		sort($threads);

		$cutoff = 86400 * $limit * ($type === 'predict' ? 0.95 : 1);
		$now = time();
		$expired = [];

		foreach ($threads as $threadNo) {
			$post = $PIO->fetchPosts($threadNo);
			if (empty($post)) continue;

			$postTime = (int)substr($post[0]['tim'], 0, 10);
			if (($now - $postTime) < $cutoff) break; // Too new to delete

			$expired[] = $threadNo;
		}

		// Prevent deleting all threads — keep at least the newest one
		if (count($expired) === count($threads) && count($expired) > 0) {
			array_pop($expired);
		}

		return $expired;
	}

	public static function info($board, $limit): string {
		return __CLASS__ . ": $limit day(s)";
	}
}
