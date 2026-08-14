<?php

namespace Kokonotsuba\Modules\postStats;

require_once __DIR__ . '/postStatsRepository.php';
require_once __DIR__ . '/postStatsService.php';

use Kokonotsuba\database\databaseConnection;
use Puchiko\background\BackgroundTaskInterface;

/**
 * Background build of the statistics cache.
 *
 * Only ever runs the first build of a scope — the one query that reads a board's whole history.
 * Once the cache exists, page views keep it current themselves a day at a time.
 *
 * The background-runner.php bootstrap provides getDatabaseSettings() and a live
 * databaseConnection singleton before handle() is called.
 */
class postStatsTask implements BackgroundTaskInterface {
	public function handle(array $args): void {
		$dbSettings = getDatabaseSettings();

		$repository = new postStatsRepository(
			databaseConnection::getInstance(),
			$dbSettings['POST_TABLE'],
			$dbSettings['POST_NUMBER_TABLE'],
			$dbSettings['POST_NUMBER_HISTORY_TABLE'] ?? ''
		);

		// No queue of its own: this is the build, it does not get to defer itself.
		$service = new postStatsService($repository, $args['cacheDirectory']);

		if (isset($args['boardUid'])) {
			$service->rebuildBoard((int)$args['boardUid'], (string)($args['startDay'] ?? ''));
		}

		if (!empty($args['siteBoardUids'])) {
			$service->rebuildSite($args['siteBoardUids'], $args['startDays'] ?? []);
		}
	}
}
