<?php
/**
 * Config schema for the fullBanner module (namespace: modules.fullBanner.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Full banner',

	'SHOW_TOP_AD' => boolField('config_label_modules.fullBanner.SHOW_TOP_AD', true, 'config_desc_modules.fullBanner.SHOW_TOP_AD'),
	'SHOW_BOTTOM_AD' => boolField('config_label_modules.fullBanner.SHOW_BOTTOM_AD', true, 'config_desc_modules.fullBanner.SHOW_BOTTOM_AD'),
	'FULLBANNER_SUBMISSION_COOLDOWN' => intField('config_label_modules.fullBanner.FULLBANNER_SUBMISSION_COOLDOWN', 300, 'config_desc_modules.fullBanner.FULLBANNER_SUBMISSION_COOLDOWN'),
	'FULLBANNER_REQUIRED_WIDTH' => intField('config_label_modules.fullBanner.FULLBANNER_REQUIRED_WIDTH', 468, 'config_desc_modules.fullBanner.FULLBANNER_REQUIRED_WIDTH'),
	'FULLBANNER_REQUIRED_HEIGHT' => intField('config_label_modules.fullBanner.FULLBANNER_REQUIRED_HEIGHT', 60, 'config_desc_modules.fullBanner.FULLBANNER_REQUIRED_HEIGHT'),
	'FULLBANNER_MAX_FILE_SIZE' => intField('config_label_modules.fullBanner.FULLBANNER_MAX_FILE_SIZE', 204800, 'config_desc_modules.fullBanner.FULLBANNER_MAX_FILE_SIZE'),
];
