<?php

namespace Kokonotsuba\thread;

use Kokonotsuba\cache\thread_fragment\threadFragments;
use Kokonotsuba\interfaces\IBoard;

use function Kokonotsuba\libraries\getPageOfThread;

/**
 * Refreshes threads that have just outlived MAX_AGE_TIME.
 *
 * A thread turning too old to bump is not a write, yet its OP now draws the old-thread notice, so
 * its cached fragments and the static page it sits on are stale. There is no cron: tick() runs at
 * the start of every request to the board, at most once per INTERVAL, and looks back over the
 * window since the last sweep. The last sweep time is kept in a file in the board's storage.
 */
final class bumpLimitSweeper {
	/** Seconds between sweeps of one board. */
	private const INTERVAL = 60;

	private const STATE_FILE = 'cache/bump_limit_sweep';

	public function __construct(
		private readonly IBoard $board,
		private readonly threadRepository $threadRepository,
	) {}

	/** Never throws: nothing the visitor asked for depends on it. */
	public function tick(?int $now = null): void {
		$maxAgeHours = (int)$this->board->getConfigValue('MAX_AGE_TIME', 0);
		if ($maxAgeHours <= 0) {
			return;
		}

		$now ??= time();

		try {
			$since = $this->claim($now);
			if ($since === null) {
				return;
			}

			// postWarnings counts a thread as old once now - created > the limit
			$maxAge = $maxAgeHours * 3600;
			$threadUids = $this->threadRepository->getThreadUidsCreatedBetween(
				$this->board->getBoardUID(),
				date('Y-m-d H:i:s', $since - $maxAge),
				date('Y-m-d H:i:s', $now - $maxAge)
			);

			if (empty($threadUids)) {
				return;
			}

			foreach ($threadUids as $threadUid) {
				threadFragments::forgetThread((string)$threadUid, $this->board->getBoardUID());
			}

			$this->rebuildStaticPages($threadUids);
		} catch (\Throwable $e) {
			error_log('[bumpLimitSweeper] board ' . $this->board->getBoardUID() . ': ' . $e->getMessage()
				. ' in ' . $e->getFile() . ':' . $e->getLine());
		}
	}

	/**
	 * Take the sweep if it is due, recording $now as the last one.
	 *
	 * @return int|null Start of the window to sweep, or null when another request holds it, the
	 *                  interval has not passed, or there was no earlier sweep to look back from.
	 */
	private function claim(int $now): ?int {
		$path = $this->board->getBoardStoragePath() . self::STATE_FILE;
		$dir = dirname($path);
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return null;
		}

		$handle = @fopen($path, 'c+');
		if ($handle === false) {
			return null;
		}

		try {
			if (!flock($handle, LOCK_EX | LOCK_NB)) {
				return null;
			}

			$last = (int)stream_get_contents($handle);
			if ($last > 0 && $last <= $now && $now - $last < self::INTERVAL) {
				return null;
			}

			ftruncate($handle, 0);
			rewind($handle);
			fwrite($handle, (string)$now);
			fflush($handle);

			// a first sweep, or a clock that went back, has no window to look over
			return ($last > 0 && $last < $now) ? $last : null;
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/** @param string[] $threadUids */
	private function rebuildStaticPages(array $threadUids): void {
		$staticHtmlUntil = (int)$this->board->getConfigValue('STATIC_HTML_UNTIL', 10);
		if ($staticHtmlUntil === 0) {
			return;
		}

		// the board's index order, as the static pages are drawn
		$threadList = $this->threadRepository->fetchThreadUIDsByBoard((string)$this->board->getBoardUID());
		$threadsPerPage = (int)$this->board->getConfigValue('PAGE_DEF', 15);

		$pages = [];
		foreach ($threadUids as $threadUid) {
			$page = getPageOfThread((string)$threadUid, $threadList, $threadsPerPage);
			if ($page >= 1 && ($staticHtmlUntil === -1 || $page <= $staticHtmlUntil)) {
				$pages[$page] = true;
			}
		}

		foreach (array_keys($pages) as $page) {
			$this->board->rebuildBoardPage($page);
		}
	}
}
