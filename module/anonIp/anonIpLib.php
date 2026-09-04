<?php

namespace Kokonotsuba\Modules\anonIp;

require_once __DIR__ . '/anonIpRunRepository.php';
require_once __DIR__ . '/anonIpScheduler.php';

use Kokonotsuba\module_classes\moduleContext;

use function Kokonotsuba\libraries\logError;

/**
 * Wiring shared by the module's front-end and admin classes, which both tick the schedule.
 */
function getAnonIpRunRepository(moduleContext $moduleContext): anonIpRunRepository {
	return new anonIpRunRepository(
		$moduleContext->databaseConnection,
		$moduleContext->getTableName('ANON_IP_RUN_TABLE')
	);
}

/**
 * Build the scheduler from the board's config.
 *
 * @param callable(string, int): int $configValue Reads a module config key with its default.
 */
function getAnonIpScheduler(moduleContext $moduleContext, callable $configValue): anonIpScheduler {
	return new anonIpScheduler(
		getAnonIpRunRepository($moduleContext),
		(int) $configValue('AUTO_ANONYMIZE_DAYS', 0),
		static function (string $message): void { logError($message); }
	);
}
