<?php
/**
 * Config schema for the privateMessage module (namespace: modules.privateMessage.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Miscellaneous" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Miscellaneous',
	'_module' => 'Private messages',

	'APPEND_TRIP_PM_BUTTON_TO_POST' => boolField('Append PM button to posts', false, 'config_desc_modules.privateMessage.APPEND_TRIP_PM_BUTTON_TO_POST'),
];
