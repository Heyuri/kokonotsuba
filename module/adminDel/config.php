<?php
/**
 * Config schema for the adminDel module (namespace: modules.adminDel.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, stringField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Janitor / delete',

	'JANIMUTE_LENGTH' => intField('config_label_modules.adminDel.JANIMUTE_LENGTH', 20, 'config_desc_modules.adminDel.JANIMUTE_LENGTH'),
	'JANIMUTE_REASON' => stringField('config_label_modules.adminDel.JANIMUTE_REASON', 'You have been muted temporarily!', 'config_desc_modules.adminDel.JANIMUTE_REASON'),
];
