<?php

namespace Kokonotsuba\Modules\threadCache;

use Kokonotsuba\cache\thread_fragment\threadFragmentCache;
use Kokonotsuba\interfaces\IBoard;

/**
 * What one board's fragment directory holds right now, read from the files themselves.
 */
final class threadCacheDiskStats {
	public const KINDS = ['index', 'thread', 'overboard', 'other'];

	/**
	 * @param array<string, int> $kinds        Fragment files per kind of variant.
	 * @param array<string, true> $threadUids  Threads with at least one fragment, by file-name token.
	 */
	private function __construct(
		public readonly int $files,
		public readonly int $bytes,
		public readonly array $kinds,
		public readonly array $threadUids,
		public readonly ?int $oldest,
		public readonly ?int $newest,
	) {}

	public static function forBoard(IBoard $board): self {
		return self::fromEntries(threadFragmentCache::forBoard($board)->entries());
	}

	/** @param list<array{threadUid: string, variant: string, size: int, mtime: int}> $entries */
	public static function fromEntries(array $entries): self {
		$bytes = 0;
		$kinds = array_fill_keys(self::KINDS, 0);
		$threadUids = [];
		$oldest = $newest = null;

		foreach ($entries as $entry) {
			$bytes += $entry['size'];
			$kinds[threadFragmentCache::parseVariant($entry['variant'])['kind'] ?? 'other']++;
			$threadUids[$entry['threadUid']] = true;
			$oldest = $oldest === null ? $entry['mtime'] : min($oldest, $entry['mtime']);
			$newest = $newest === null ? $entry['mtime'] : max($newest, $entry['mtime']);
		}

		return new self(count($entries), $bytes, $kinds, $threadUids, $oldest, $newest);
	}

	public function threadCount(): int {
		return count($this->threadUids);
	}

	/** @param self[] $stats */
	public static function sum(array $stats): self {
		$kinds = array_fill_keys(self::KINDS, 0);
		$threadUids = [];
		$files = $bytes = 0;
		$oldest = $newest = null;

		foreach ($stats as $i => $stat) {
			$files += $stat->files;
			$bytes += $stat->bytes;
			foreach ($stat->kinds as $kind => $count) {
				$kinds[$kind] += $count;
			}
			// thread uids are only unique within a board
			foreach ($stat->threadUids as $uid => $_) {
				$threadUids[$i . ':' . $uid] = true;
			}
			if ($stat->oldest !== null) {
				$oldest = $oldest === null ? $stat->oldest : min($oldest, $stat->oldest);
				$newest = $newest === null ? $stat->newest : max($newest, $stat->newest);
			}
		}

		return new self($files, $bytes, $kinds, $threadUids, $oldest, $newest);
	}
}
