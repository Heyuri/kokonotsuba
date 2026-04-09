<?php

namespace Kokonotsuba\Modules\fullBanner;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

function getFullBannerRepository(): fullBannerRepository {
	$databaseSettings = getDatabaseSettings();
	$databaseConnection = databaseConnection::getInstance();
	return new fullBannerRepository(
		$databaseConnection,
		$databaseSettings['BANNER_AD_TABLE']
	);
}

function getFullBannerService(transactionManager $transactionManager): fullBannerService {
	$storageDir = getBackendGlobalDir() . 'fullbanners/';
	return new fullBannerService(
		getFullBannerRepository(),
		$transactionManager,
		$storageDir
	);
}
