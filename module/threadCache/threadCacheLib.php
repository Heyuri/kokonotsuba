<?php

namespace Kokonotsuba\Modules\threadCache;

require_once __DIR__ . '/threadCacheHitRepository.php';

use Kokonotsuba\module_classes\moduleContext;

/** Wiring shared by the module's front-end and admin classes. */
function getThreadCacheHitRepository(moduleContext $moduleContext): threadCacheHitRepository {
	return new threadCacheHitRepository(
		$moduleContext->databaseConnection,
		$moduleContext->getTableName('THREAD_FRAGMENT_HIT_TABLE'),
		$moduleContext->getTableName('THREAD_TABLE')
	);
}
