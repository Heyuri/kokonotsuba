<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpRepository.php';
require_once __DIR__ . '/anonIpService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Puchiko\background\BackgroundTaskInterface;

/**
 * Background task that anonymizes IP addresses in posts and the action log.
 *
 * The background-runner.php bootstrap provides getDatabaseSettings() and a
 * live databaseConnection singleton before handle() is called.
 */
class anonIpTask implements BackgroundTaskInterface {
	public function handle(array $args): void {
		$timeframe = $args['timeframe'] ?? '';

		$dbSettings = getDatabaseSettings();
		$conn       = databaseConnection::getInstance();

		$repo = new anonIpRepository(
			$conn,
			$dbSettings['POST_TABLE'],
			$dbSettings['ACTIONLOG_TABLE']
		);

		$service = new anonIpService($repo, new transactionManager($conn));

		if ($timeframe === 'now') {
			$service->anonymizeAll();
		} else {
			$service->anonymizeByTimeframe($timeframe);
		}
	}
}
