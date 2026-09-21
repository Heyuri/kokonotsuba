<?php

namespace Kokonotsuba\cache\thread_fragment;

use Kokonotsuba\board\board;
use Kokonotsuba\post\Post;

use function Kokonotsuba\libraries\searchBoardArrayForBoard;

/**
 * Invalidation entry points. Call them after the change is committed, so a render racing the
 * write cannot store the old markup after it was dropped.
 *
 * A thread's fragments go when the thread or any post in it changes: a reply, an edit, a
 * deletion or restore, a ban notice, a vote, a flag toggle, a theme or a move. Being quoted is
 * not a change: backlinks are drawn client-side. A whole board's go only when everything may
 * render differently: a manual rebuild or a config save.
 */
final class threadFragments {
	public static function forgetThread(string $threadUid, int $boardUid): void {
		if ($threadUid === '' || !defined('GLOBAL_BOARD_ARRAY')) {
			return;
		}
		$board = searchBoardArrayForBoard($boardUid);
		if ($board !== null) {
			threadFragmentCache::forBoard($board)->forget($threadUid);
		}
	}

	/**
	 * Rows of thread_uid and boardUID, and storage_directory_name when the query joined the board.
	 * With the name the board list is not needed, so a CLI run without it still invalidates.
	 *
	 * @param array|false $pairs As postRepository::getThreadsTouchedByPosts() returns them.
	 */
	public static function forgetThreadPairs(array|false $pairs): void {
		foreach ($pairs ?: [] as $pair) {
			$threadUid = (string)($pair['thread_uid'] ?? '');
			$storageDirName = (string)($pair['storage_directory_name'] ?? '');
			if ($threadUid !== '' && $storageDirName !== '' && function_exists('getBoardStoragesDir')) {
				threadFragmentCache::forStorageDirName($storageDirName, getBoardStoragesDir())->forget($threadUid);
				continue;
			}
			self::forgetThread($threadUid, (int)($pair['boardUID'] ?? 0));
		}
	}

	/** @param Post[] $posts */
	public static function forgetPosts(array $posts): void {
		$seen = [];
		foreach ($posts as $post) {
			$key = $post->getBoardUID() . ':' . $post->getThreadUid();
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			self::forgetThread($post->getThreadUid(), $post->getBoardUID());
		}
	}

	public static function forgetBoard(board $board): void {
		threadFragmentCache::forBoard($board)->clear();
	}
}
