<?php

namespace Kokonotsuba\Modules\postStats;

use Puchiko\background\BackgroundTaskDispatcher;
use Throwable;

/**
 * Where a build that is too expensive to run inside a page view gets handed off to.
 *
 * Only the very first build for a scope is ever handed off: it reads the whole post history,
 * which is the one query here that scales with the size of the board rather than with a day's
 * traffic. Everything after it reads a day or so of rows and stays inline.
 */
interface postStatsBuildQueue {
	/**
	 * Ask for a scope to be built out of band.
	 *
	 * @param string $scope A stable name for what is being built, e.g. 'board-7' or 'site'.
	 * @param array  $args  Arguments for the build.
	 * @return bool True if the build was handed off and the caller should not build inline.
	 */
	public function request(string $scope, array $args): bool;
}

/**
 * Hands builds to the background task runner, with a marker file so that every view during a
 * long build does not spawn another process. A failure to dispatch (exec() disabled, no PHP CLI)
 * returns false so the caller falls back to building inline — slow beats never.
 */
class postStatsBackgroundQueue implements postStatsBuildQueue {
	public const TASK_NAME = 'post_stats_build';

	public function __construct(
		private readonly string $directory,
		private readonly int $cooldownSeconds = 300,
	) {}

	public function request(string $scope, array $args): bool {
		$marker = $this->directory . '.building-' . $scope;

		// A build already went out recently. Assume it is still running rather than starting
		// a second one; the marker ages out on its own if the process died.
		if (is_file($marker) && (time() - (int)filemtime($marker)) < $this->cooldownSeconds) {
			return true;
		}

		if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
			return false;
		}

		if (@file_put_contents($marker, (string)time()) === false) {
			return false;
		}

		try {
			BackgroundTaskDispatcher::dispatch(self::TASK_NAME, $args);
		} catch (Throwable) {
			@unlink($marker);
			return false;
		}

		return true;
	}
}
