<?php

namespace Kokonotsuba\Modules\lockDown;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\module_classes\moduleContext;

include_once __DIR__ . '/lockDownRepository.php';
include_once __DIR__ . '/lockDownFlag.php';

function makeLockDownRepository(moduleContext $context): lockDownRepository {
	return new lockDownRepository(
		databaseConnection::getInstance(),
		$context->getTableName('POST_TABLE'),
		$context->getTableName('DELETED_POSTS_TABLE')
	);
}

function makeLockDownFlag(moduleContext $context): lockDownFlag {
	return new lockDownFlag($context->board->getBoardStoragePath());
}
