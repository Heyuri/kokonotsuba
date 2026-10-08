<?php

namespace Kokonotsuba\Modules\threadCache;

require_once __DIR__ . '/threadCacheLib.php';

use Kokonotsuba\cache\thread_fragment\threadFragmentHitCounter;
use Kokonotsuba\module_classes\abstractModuleMain;

/**
 * Counts how often each cached thread is served from the cache, for the admin page.
 *
 * Hits and misses are gathered in memory and written in one statement when the request ends.
 * Only web requests count: the CLI and background tasks draw for no reader.
 */
class moduleMain extends abstractModuleMain {
	public function getName(): string {
		return 'Thread cache';
	}

	public function getVersion(): string {
		return 'Koko 2026';
	}

	public function initialize(): void {
		if (PHP_SAPI === 'cli' || threadFragmentHitCounter::isCounting()) {
			return;
		}

		$repository = getThreadCacheHitRepository($this->moduleContext);
		threadFragmentHitCounter::start(static function (array $rows) use ($repository): void {
			$repository->record($rows);
		});
	}
}
