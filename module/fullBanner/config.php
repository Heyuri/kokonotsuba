<?php
/**
 * Config schema for the fullBanner module (namespace: modules.fullBanner.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Full banner',

	'SHOW_TOP_AD' => boolField('Show top full banner', true, 'Show the top full-banner ad.'),
	'SHOW_BOTTOM_AD' => boolField('Show bottom full banner', true, 'Show the bottom full-banner ad.'),
	'FULLBANNER_SUBMISSION_COOLDOWN' => intField('Banner submission cooldown (s)', 300, 'Seconds between banner submissions per IP.'),
	'FULLBANNER_REQUIRED_WIDTH' => intField('Banner required width', 468, 'Required banner image width in pixels.'),
	'FULLBANNER_REQUIRED_HEIGHT' => intField('Banner required height', 60, 'Required banner image height in pixels.'),
	'FULLBANNER_MAX_FILE_SIZE' => intField('Banner max file size (bytes)', 204800, 'Maximum banner file size in bytes.'),
];
