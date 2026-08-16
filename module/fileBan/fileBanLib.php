<?php

namespace Kokonotsuba\Modules\fileBan;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

function getFileBanRepository(): fileBanRepository {
	$databaseConnection = databaseConnection::getInstance();

	return new fileBanRepository(
		$databaseConnection,
		getTableName('FILE_BAN_TABLE'),
		getTableName('ACCOUNT_TABLE')
	);
}

function getFileBanService(transactionManager $transactionManager): fileBanService {
	return new fileBanService(
		getFileBanRepository(),
		$transactionManager
	);
}
