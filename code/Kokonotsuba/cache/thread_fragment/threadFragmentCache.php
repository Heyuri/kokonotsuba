<?php

namespace Kokonotsuba\cache\thread_fragment;

use Kokonotsuba\interfaces\IBoard;
use Kokonotsuba\thread\Thread;

/**
 * Rendered thread markup for anonymous readers, one file per thread and variant, kept under the
 * thread's board storage directory.
 *
 * Fragments are dropped through threadFragments when their thread changes, and a whole board's
 * on a manual rebuild or config save. Misses are drawn and stored by whoever needs them: the
 * overboard, live pages and the static rebuild alike.
 *
 * Only markup that is the same for every anonymous reader belongs in a fragment. Anything that
 * depends on the page it sits on (navigation arrows, separators) is added by the caller.
 *
 * A file also carries a stamp of the thread's post count and last reply time, taken from the
 * row the reader is served from. A reply or deletion changes the stamp, so a fragment stored by
 * a render that raced the change is a miss anyway; the explicit forget covers everything else.
 *
 * A change that leaves the stamp alone (an edit, a vote, a flag) can land while a reader is still
 * drawing from the rows as they were. Forgetting therefore moves an epoch before it deletes, a
 * miss notes the epoch before the posts are fetched, and a fragment drawn across a move is not
 * stored. Epochs are shared by threads in 256 shards, so they stay a fixed handful of files.
 */
final class threadFragmentCache {
	/** Bumped when the stored shape changes, so files written by older code are never read. */
	private const FORMAT = 2;

	private const SUBDIR = 'cache/threads/';

	private const EPOCH_DIR = 'epochs/';

	/** The epoch every thread of the board shares, moved by clear(). */
	private const BOARD_EPOCH = 'all';

	/** Marks where a page's navigation arrows go in a stored block; the page swaps in its own. */
	public const NAV_SENTINEL = '<!--koko:threadnav-->';

	/** @var array<string,string> The epoch each missed fragment has to be stored under. */
	private array $notedEpochs = [];

	public function __construct(private readonly string $directory) {}

	public static function forBoard(IBoard $board): self {
		return new self($board->getBoardStoragePath() . self::SUBDIR);
	}

	/** The store for a board known only by its storage directory name, as a database row gives it. */
	public static function forStorageDirName(string $storageDirName, string $storagesDir): self {
		return new self(rtrim($storagesDir, '/') . '/' . $storageDirName . '/' . self::SUBDIR);
	}

	/** What a fragment of this thread must have been drawn from to still be current. */
	public static function stampFor(Thread $thread): string {
		return $thread->getPostCount() . '-' . preg_replace('/\D+/', '', $thread->getLastReplyTime());
	}

	/** Board index preview, drawn with the board's own reply preview count. */
	public static function indexVariant(int $previewCount): string {
		return 'index-' . $previewCount;
	}

	/**
	 * Overboard preview: carries the board title line and cross-board links, and is drawn with
	 * the viewing board's template, so each board's overboard keeps its own.
	 */
	public static function overboardVariant(int $previewCount, int $viewingBoardUid): string {
		return 'overboard-' . $viewingBoardUid . '-' . $previewCount;
	}

	/** A thread page; null is the whole thread on one page. */
	public static function threadVariant(?int $page): string {
		return 'thread-' . ($page ?? 'all');
	}

	/** The stored markup, or null when there is none. Call it before fetching what a miss is drawn from. */
	public function get(string $threadUid, string $variant, string $stamp): ?string {
		$path = $this->path($threadUid, $variant, $stamp);
		$html = is_file($path) ? @file_get_contents($path) : false;
		if ($html !== false) {
			return $html;
		}

		// the directory has to exist for a forget to have somewhere to move the epoch
		if (is_dir($this->directory) || @mkdir($this->directory, 0755, true) || is_dir($this->directory)) {
			$this->notedEpochs[$threadUid . "\0" . $variant] = $this->epoch($threadUid);
		}

		return null;
	}

	/**
	 * Store markup atomically. Returns false when the directory cannot be written, or when the
	 * thread was forgotten since the miss this was drawn for; callers ignore both.
	 */
	public function put(string $threadUid, string $variant, string $stamp, string $html): bool {
		if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
			return false;
		}

		$noted = $this->notedEpochs[$threadUid . "\0" . $variant] ?? null;
		unset($this->notedEpochs[$threadUid . "\0" . $variant]);
		if ($noted !== null && $noted !== $this->epoch($threadUid)) {
			return false;
		}

		$path = $this->path($threadUid, $variant, $stamp);
		$temp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

		if (@file_put_contents($temp, $html) === false) {
			return false;
		}
		if (!@rename($temp, $path)) {
			@unlink($temp);
			return false;
		}

		// a forget that moved the epoch before this check takes the file back here; one that moves
		// it afterwards finds the file in place and deletes it
		if ($noted !== null && $noted !== $this->epoch($threadUid)) {
			@unlink($path);
			return false;
		}

		// earlier stamps of the same variant can never be read again
		foreach (glob($this->directory . self::fileToken($threadUid) . '.' . self::fileToken($variant) . '.*.html') ?: [] as $file) {
			if ($file !== $path) {
				@unlink($file);
			}
		}

		return true;
	}

	/** Drop every variant of one thread. */
	public function forget(string $threadUid): void {
		// nothing was ever read here, so nothing is stored and nobody is drawing
		if (!is_dir($this->directory)) {
			return;
		}
		$this->moveEpoch(self::epochShard($threadUid));

		foreach (glob($this->directory . self::fileToken($threadUid) . '.*.html') ?: [] as $file) {
			@unlink($file);
		}
	}

	/** Drop every fragment of the board. */
	public function clear(): void {
		if (!is_dir($this->directory)) {
			return;
		}
		$this->moveEpoch(self::BOARD_EPOCH);

		foreach (scandir($this->directory) ?: [] as $entry) {
			if (str_ends_with($entry, '.html') || str_ends_with($entry, '.tmp')) {
				@unlink($this->directory . $entry);
			}
		}
	}

	/** What has to be unchanged between a miss and its store. */
	private function epoch(string $threadUid): string {
		$dir = $this->directory . self::EPOCH_DIR;

		return @file_get_contents($dir . self::BOARD_EPOCH) . ':' . @file_get_contents($dir . self::epochShard($threadUid));
	}

	/** Never the same value twice, and swapped in whole so a reader cannot see half of one. */
	private function moveEpoch(string $name): void {
		$dir = $this->directory . self::EPOCH_DIR;
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return;
		}

		$temp = $dir . $name . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (@file_put_contents($temp, bin2hex(random_bytes(8))) === false || !@rename($temp, $dir . $name)) {
			@unlink($temp);
		}
	}

	private static function epochShard(string $threadUid): string {
		return substr(sha1($threadUid), 0, 2);
	}

	private function path(string $threadUid, string $variant, string $stamp): string {
		return $this->directory . self::fileToken($threadUid) . '.' . self::fileToken($variant) . '.' . self::fileToken($stamp) . '.v' . self::FORMAT . '.html';
	}

	/** A file-name-safe token: the value itself when it already is one, otherwise a hash of it. */
	private static function fileToken(string $value): string {
		return preg_match('/^[A-Za-z0-9_-]{1,64}\z/', $value) ? $value : sha1($value);
	}
}
