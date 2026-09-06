<?php

namespace Kokonotsuba\Modules\perceptualBan;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

function getPerceptualBanRepository(): perceptualBanRepository {
	$databaseConnection = databaseConnection::getInstance();

	return new perceptualBanRepository(
		$databaseConnection,
		getTableName('PERCEPTUAL_BAN_TABLE'),
		getTableName('ACCOUNT_TABLE')
	);
}

function getPerceptualHasher(): perceptualHasher {
	return new perceptualHasher();
}

function getPerceptualBanService(transactionManager $transactionManager): perceptualBanService {
	return new perceptualBanService(
		getPerceptualBanRepository(),
		getPerceptualHasher(),
		$transactionManager
	);
}
