<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpTarget.php';
require_once __DIR__ . '/anonIpTargets.php';
require_once __DIR__ . '/anonIpRepository.php';
require_once __DIR__ . '/anonIpRunRepository.php';
require_once __DIR__ . '/anonIpService.php';

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;
use Kokonotsuba\ip\ipAnonymizer;
use Puchiko\background\BackgroundTaskInterface;

/**
 * Background task that anonymizes every IP-bearing column listed in anonIpTargets.
 *
 * Called two ways. Staff pressing the button pass a 'timeframe' from the page's dropdown; the
 * schedule passes 'olderThanDays' from the configured retention window. Both may carry a 'runId'
 * naming the ledger row to close out when the work is done.
 *
 * The background-runner.php bootstrap provides getDatabaseSettings()/getTableNames() and a
 * live databaseConnection singleton before handle() is called.
 */
class anonIpTask implements BackgroundTaskInterface {
	public function handle(array $args): void {
		$tableNames = getTableNames();
		$dbSettings = getDatabaseSettings();
		$conn       = databaseConnection::getInstance();

		$anonymizer = new ipAnonymizer((string) ($dbSettings['ANON_IP_SALT'] ?? ''));

		$service = new anonIpService(
			new anonIpRepository($conn, $tableNames['POST_TABLE'], $anonymizer),
			new transactionManager($conn),
			anonIpTargets::build($tableNames, (new \DateTimeImmutable())->format('Y-m-d H:i:s')),
		);

		$changed = $this->runFor($service, $args);

		$runId = isset($args['runId']) ? (int) $args['runId'] : 0;

		// A run with nothing to close out (an older dispatch, or a direct call) still did the work.
		if ($runId > 0) {
			(new anonIpRunRepository($conn, $tableNames['ANON_IP_RUN_TABLE']))
				->markFinished($runId, max(0, $changed), $service->getLastBreakdown());
		}
	}

	/**
	 * Run whichever scope the dispatch asked for.
	 *
	 * @return int Rows anonymized, or -1 when the time frame was unrecognized.
	 */
	private function runFor(anonIpService $service, array $args): int {
		if (array_key_exists('olderThanDays', $args)) {
			$days = $args['olderThanDays'];

			return $days === null ? $service->anonymizeAll() : $service->anonymizeOlderThanDays((int) $days);
		}

		$timeframe = $args['timeframe'] ?? '';

		return $timeframe === 'now' ? $service->anonymizeAll() : $service->anonymizeByTimeframe($timeframe);
	}
}
