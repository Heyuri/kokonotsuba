<?php
/**
 * Config schema for the onlineCounter module (namespace: modules.onlineCounter.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Overboard, ads & banners" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, stringField};

return [
	'_group'  => 'Overboard, ads & banners',
	'_module' => 'Online counter',

	'USER_COUNT_DAT_FILE' => stringField('Online counter data file', 'users.dat', 'Filename used to track viewing IPs (stored in the board storage dir).'),
	'USER_COUNT_TIMEOUT' => intField('Online counter timeout (min)', 10, 'How long an IP counts as online, in minutes.'),
];
