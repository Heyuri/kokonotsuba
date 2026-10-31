<?php

namespace Kokonotsuba\front_pages;

use Closure;
use Kokonotsuba\post\postRepository;

/**
 * Posts made on listed boards in the last hour and the last 24 hours, for the front page.
 * Kept in a small file for a minute so most front page hits run no query.
 */
final class frontPostStats {
	private const HOUR = 3600;
	private const DAY = 86400;

	/**
	 * @param Closure(): int[] $listedBoardUids Listed boards, asked for only when the counts are refreshed.
	 * @param Closure(): int   $clock           Current Unix time.
	 */
	public function __construct(
		private readonly postRepository $postRepository,
		private readonly Closure $listedBoardUids,
		private readonly string $file,
		private readonly int $ttl = 60,
		private readonly ?Closure $clock = null,
	) {}

	/** @return array{hour: int, day: int} */
	public function counts(): array {
		$now = $this->clock !== null ? ($this->clock)() : time();

		$cached = $this->read();
		if ($cached !== null && $now - $cached['at'] < $this->ttl && $cached['at'] <= $now) {
			return ['hour' => $cached['hour'], 'day' => $cached['day']];
		}

		// root is stored in UTC
		$counts = $this->postRepository->countRecentPosts(
			($this->listedBoardUids)(),
			gmdate('Y-m-d H:i:s', $now - self::HOUR),
			gmdate('Y-m-d H:i:s', $now - self::DAY),
		);
		$this->write(['at' => $now] + $counts);

		return $counts;
	}

	/** @return array{at: int, hour: int, day: int}|null */
	private function read(): ?array {
		$json = @file_get_contents($this->file);
		$data = $json === false ? null : json_decode($json, true);
		if (!is_array($data) || !isset($data['at'], $data['hour'], $data['day'])) {
			return null;
		}

		return ['at' => (int)$data['at'], 'hour' => (int)$data['hour'], 'day' => (int)$data['day']];
	}

	private function write(array $data): void {
		$directory = dirname($this->file);
		if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
			return;
		}

		// written beside and renamed, so a concurrent reader never sees half a file
		$temporary = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (@file_put_contents($temporary, json_encode($data)) !== false) {
			@rename($temporary, $this->file);
		}
	}
}
