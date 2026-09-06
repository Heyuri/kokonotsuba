<?php
/**
 * Config schema for the banner module (namespace: modules.banner.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 *
 * The {PRESET}_* keys are what bannerPresetRegistry reads; a preset's key prefix is fixed there.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Banners',

	'SHOW_TOP_AD' => boolField('config_label_modules.banner.SHOW_TOP_AD', true, 'config_desc_modules.banner.SHOW_TOP_AD'),
	'SHOW_BOTTOM_AD' => boolField('config_label_modules.banner.SHOW_BOTTOM_AD', true, 'config_desc_modules.banner.SHOW_BOTTOM_AD'),
	'SHOW_BOARD_BANNER' => boolField('config_label_modules.banner.SHOW_BOARD_BANNER', true, 'config_desc_modules.banner.SHOW_BOARD_BANNER'),

	// Banner ad preset
	'BANNER_AD_ALLOW_SUBMISSIONS' => boolField('config_label_modules.banner.BANNER_AD_ALLOW_SUBMISSIONS', true, 'config_desc_modules.banner.BANNER_AD_ALLOW_SUBMISSIONS'),
	'BANNER_AD_SUBMISSION_COOLDOWN' => intField('config_label_modules.banner.BANNER_AD_SUBMISSION_COOLDOWN', 300, 'config_desc_modules.banner.BANNER_AD_SUBMISSION_COOLDOWN'),
	'BANNER_AD_WIDTH' => intField('config_label_modules.banner.BANNER_AD_WIDTH', 468, 'config_desc_modules.banner.BANNER_AD_WIDTH'),
	'BANNER_AD_HEIGHT' => intField('config_label_modules.banner.BANNER_AD_HEIGHT', 60, 'config_desc_modules.banner.BANNER_AD_HEIGHT'),
	'BANNER_AD_MAX_FILE_SIZE' => intField('config_label_modules.banner.BANNER_AD_MAX_FILE_SIZE', 204800, 'config_desc_modules.banner.BANNER_AD_MAX_FILE_SIZE'),

	// Board banner preset
	'BOARD_BANNER_ALLOW_SUBMISSIONS' => boolField('config_label_modules.banner.BOARD_BANNER_ALLOW_SUBMISSIONS', true, 'config_desc_modules.banner.BOARD_BANNER_ALLOW_SUBMISSIONS'),
	'BOARD_BANNER_SUBMISSION_COOLDOWN' => intField('config_label_modules.banner.BOARD_BANNER_SUBMISSION_COOLDOWN', 300, 'config_desc_modules.banner.BOARD_BANNER_SUBMISSION_COOLDOWN'),
	'BOARD_BANNER_WIDTH' => intField('config_label_modules.banner.BOARD_BANNER_WIDTH', 300, 'config_desc_modules.banner.BOARD_BANNER_WIDTH'),
	'BOARD_BANNER_HEIGHT' => intField('config_label_modules.banner.BOARD_BANNER_HEIGHT', 100, 'config_desc_modules.banner.BOARD_BANNER_HEIGHT'),
	'BOARD_BANNER_MAX_FILE_SIZE' => intField('config_label_modules.banner.BOARD_BANNER_MAX_FILE_SIZE', 204800, 'config_desc_modules.banner.BOARD_BANNER_MAX_FILE_SIZE'),
];
