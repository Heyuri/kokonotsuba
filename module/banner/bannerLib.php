<?php

namespace Kokonotsuba\Modules\banner;

use Kokonotsuba\database\databaseConnection;
use Kokonotsuba\database\transactionManager;

use function Kokonotsuba\libraries\_T;
use function Puchiko\strings\sanitizeStr;

/** Where uploaded banners of every preset are kept, outside the web root. */
function getBannerStorageDir(): string {
	return getBackendGlobalDir() . 'banners/';
}

function getBannerRepository(): bannerRepository {
	return new bannerRepository(
		databaseConnection::getInstance(),
		getTableName('BANNER_AD_TABLE')
	);
}

function getBannerService(transactionManager $transactionManager): bannerService {
	return new bannerService(
		getBannerRepository(),
		$transactionManager,
		getBannerStorageDir()
	);
}

/**
 * The preset switcher shown above the public and admin banner tables.
 *
 * @param ?string $currentKey the selected preset, or null for the "all presets" view
 * @param bool $withAll whether an "all presets" entry belongs in the list (admin only)
 */
function renderPresetNav(bannerPresetRegistry $presets, string $baseUrl, ?string $currentKey, bool $withAll = false): string {
	$entries = [];

	foreach ($presets->all() as $key => $preset) {
		$entries[] = [$preset->label(), $baseUrl . '&preset=' . urlencode($key), $key === $currentKey];
	}

	if ($withAll) {
		$entries[] = [_T('banner_preset_all'), $baseUrl . '&preset=all', $currentKey === null];
	}

	$links = [];
	foreach ($entries as [$label, $url, $isCurrent]) {
		$links[] = $isCurrent
			? '<b class="bannerPresetCurrent">' . sanitizeStr($label) . '</b>'
			: '<a href="' . sanitizeStr($url) . '">' . sanitizeStr($label) . '</a>';
	}

	return '<div class="bannerPresetNav">' . implode(' | ', $links) . '</div>';
}
