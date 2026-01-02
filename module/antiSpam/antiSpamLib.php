<?php

namespace Kokonotsuba\Modules\antiSpam;

use DatabaseConnection;
use Kokonotsuba\Modules\antiSpam\antiSpamRepository;
use Kokonotsuba\Modules\antiSpam\antiSpamService;

function getAntiSpamService(): antiSpamService {
    // get database settings
	$databaseSettings = getDatabaseSettings();

	// get database connection
	$databaseConnection = DatabaseConnection::getInstance();

	// initialize repo
	$antiSpamRepository = new antiSpamRepository($databaseConnection, $databaseSettings['SPAM_STRING_TABLE'], $databaseSettings['ACCOUNT_TABLE']);

	// then init and return antiSpamService
	return new antiSpamService($antiSpamRepository);
}