<?php

namespace Kokonotsuba\Modules\perceptualBan;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

function getPerceptualBanRepository(): perceptualBanRepository {
	$databaseSettings = getDatabaseSettings();
	$databaseConnection = databaseConnection::getInstance();

	return new perceptualBanRepository(
		$databaseConnection,
		$databaseSettings['PERCEPTUAL_BAN_TABLE'],
		$databaseSettings['ACCOUNT_TABLE']
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
