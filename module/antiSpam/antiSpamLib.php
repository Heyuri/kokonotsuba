<?php

namespace Kokonotsuba\Modules\antiSpam;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\Modules\antiSpam\antiSpamRepository;
use Kokonotsuba\Modules\antiSpam\antiSpamService;

function getAntiSpamService(): antiSpamService {
    // get database settings

	// get database connection
	$databaseConnection = databaseConnection::getInstance();

	// initialize repo
	$antiSpamRepository = new antiSpamRepository($databaseConnection, getTableName('SPAM_STRING_TABLE'), getTableName('ACCOUNT_TABLE'));

	// then init and return antiSpamService
	return new antiSpamService($antiSpamRepository);
}