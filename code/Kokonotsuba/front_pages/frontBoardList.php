<?php

namespace Kokonotsuba\front_pages;

use Closure;
use Kokonotsuba\board\board;
use Kokonotsuba\post\postRepository;

use function Puchiko\writeFileAtomically;

/**
 * The listed boards for the front page board table, with their activity when stats are on.
 * The counts are kept in a small file for a minute so most front page hits run no stats query.
 */
final class frontBoardList {
	private const HOUR = 3600;
	private const DAY = 86400;
	private const USERS_WINDOW = 3 * 86400;

	/**
	 * @param Closure(): board[] $listedBoards   Listed boards, asked for on every draw.
	 * @param ?postRepository    $postRepository Null when the stat columns are off.
	 * @param Closure(): int     $clock          Current Unix time.
	 */
	public function __construct(
		private readonly Closure $listedBoards,
		private readonly ?postRepository $postRepository,
		private readonly string $file,
		private readonly int $ttl = 60,
		private readonly ?Closure $clock = null,
	) {}

	public function hasStats(): bool {
		return $this->postRepository !== null;
	}

	/**
	 * Boards in display order: most users first, then most posts; listed order when stats are off.
	 *
	 * @return array<int, array{board: board, hour: int, day: int, users: int, posts: int}>
	 */
	public function rows(): array {
		$boards = ($this->listedBoards)();
		$activity = $this->hasStats() ? $this->activity(array_map(fn(board $b): int => $b->getBoardUID(), $boards)) : [];

		$rows = [];
		foreach ($boards as $board) {
			$counts = $activity[$board->getBoardUID()] ?? ['hour' => 0, 'day' => 0, 'users' => 0, 'posts' => 0];
			$rows[] = ['board' => $board] + $counts;
		}

		if ($this->hasStats()) {
			usort($rows, fn(array $a, array $b): int => [$b['users'], $b['posts']] <=> [$a['users'], $a['posts']]);
		}

		return $rows;
	}

	/**
	 * @param int[] $boardUids
	 * @return array<int, array{hour: int, day: int, users: int, posts: int}>
	 */
	private function activity(array $boardUids): array {
		$now = $this->clock !== null ? ($this->clock)() : time();

		$cached = $this->read();
		if ($cached !== null && $now - $cached['at'] < $this->ttl && $cached['at'] <= $now) {
			return $cached['boards'];
		}

		// root is stored in UTC
		$activity = $this->postRepository->boardActivity(
			$boardUids,
			gmdate('Y-m-d H:i:s', $now - self::HOUR),
			gmdate('Y-m-d H:i:s', $now - self::DAY),
			gmdate('Y-m-d H:i:s', $now - self::USERS_WINDOW),
		);
		$this->write(['at' => $now, 'boards' => $activity]);

		return $activity;
	}

	/** @return array{at: int, boards: array}|null */
	private function read(): ?array {
		$json = @file_get_contents($this->file);
		$data = $json === false ? null : json_decode($json, true);
		if (!is_array($data) || !isset($data['at']) || !is_array($data['boards'] ?? null)) {
			return null;
		}

		$boards = [];
		foreach ($data['boards'] as $uid => $counts) {
			$boards[(int)$uid] = [
				'hour'  => (int)($counts['hour'] ?? 0),
				'day'   => (int)($counts['day'] ?? 0),
				'users' => (int)($counts['users'] ?? 0),
				'posts' => (int)($counts['posts'] ?? 0),
			];
		}

		return ['at' => (int)$data['at'], 'boards' => $boards];
	}

	private function write(array $data): void {
		writeFileAtomically($this->file, (string)json_encode($data));
	}
}
