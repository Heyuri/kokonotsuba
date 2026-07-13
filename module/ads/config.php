<?php
/**
 * Config schema for the ads module (namespace: modules.ads.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{arrayField, intField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Ads',

	'ADS_STICKY_ROTATE_SECONDS' => intField('Sticky ad rotate (s)', 45, 'config_desc_modules.ads.ADS_STICKY_ROTATE_SECONDS'),
	'ADS_INLINE_EVERY_N_THREADS' => intField('Inline ad every N threads', 4, 'config_desc_modules.ads.ADS_INLINE_EVERY_N_THREADS'),
	'ADS_INLINE_COUNT' => intField('Inline ads per row', 2, 'config_desc_modules.ads.ADS_INLINE_COUNT'),
	'ADS_POST_AD_EVERY_N_POSTS' => intField('Post ad every N posts', 15, 'config_desc_modules.ads.ADS_POST_AD_EVERY_N_POSTS'),
	'ADS_SLOT_DIMENSIONS' => arrayField('Ad slot dimensions', [
		'top'     => '728x90',
		'mobile'  => '300x250',
		'sticky'  => '728x90',
		'above'   => '728x150',
		'below'   => '728x150',
		'inline'  => '728x150',
		'post_ad' => '300x250',
	], 'config_desc_modules.ads.ADS_SLOT_DIMENSIONS'),
];
