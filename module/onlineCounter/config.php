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

	'USER_COUNT_DAT_FILE' => stringField('Online counter data file', 'users.dat', 'config_desc_modules.onlineCounter.USER_COUNT_DAT_FILE'),
	'USER_COUNT_TIMEOUT' => intField('Online counter timeout (min)', 10, 'config_desc_modules.onlineCounter.USER_COUNT_TIMEOUT'),
];
