<?php

namespace Kokonotsuba\Modules\fileBan;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

function getFileBanRepository(): fileBanRepository {
	$databaseSettings = getDatabaseSettings();
	$databaseConnection = databaseConnection::getInstance();

	return new fileBanRepository(
		$databaseConnection,
		$databaseSettings['FILE_BAN_TABLE'],
		$databaseSettings['ACCOUNT_TABLE']
	);
}

function getFileBanService(transactionManager $transactionManager): fileBanService {
	return new fileBanService(
		getFileBanRepository(),
		$transactionManager
	);
}
