<?php
/**
 * Config schema for the onlineCounter module (namespace: modules.onlineCounter.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, stringField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Online counter',

	'USER_COUNT_DAT_FILE' => stringField('config_label_modules.onlineCounter.USER_COUNT_DAT_FILE', 'users.dat', 'config_desc_modules.onlineCounter.USER_COUNT_DAT_FILE'),
	// min 1: a 0 timeout renders data-timeout="0", which the updater JS would turn into a
	// zero-delay poll loop (see onlineCounterUpdater.js).
	'USER_COUNT_TIMEOUT' => intField('config_label_modules.onlineCounter.USER_COUNT_TIMEOUT', 10, 'config_desc_modules.onlineCounter.USER_COUNT_TIMEOUT', min: 1),
];
