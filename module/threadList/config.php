<?php
/**
 * Config schema for the threadList module (namespace: modules.threadList.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Thread list',

	'THREADLIST_NUMBER' => intField('config_label_modules.threadList.THREADLIST_NUMBER', 50, 'config_desc_modules.threadList.THREADLIST_NUMBER'),
	'FORCE_SUBJECT' => boolField('config_label_modules.threadList.FORCE_SUBJECT', true, 'config_desc_modules.threadList.FORCE_SUBJECT'),
	'SHOW_IN_MAIN' => boolField('config_label_modules.threadList.SHOW_IN_MAIN', true, 'config_desc_modules.threadList.SHOW_IN_MAIN'),
	'THREADLIST_NUMBER_IN_MAIN' => intField('config_label_modules.threadList.THREADLIST_NUMBER_IN_MAIN', 40, 'config_desc_modules.threadList.THREADLIST_NUMBER_IN_MAIN'),
	'SHOW_FORM' => boolField('config_label_modules.threadList.SHOW_FORM', false, 'config_desc_modules.threadList.SHOW_FORM'),
	'HIGHLIGHT_COUNT' => intField('config_label_modules.threadList.HIGHLIGHT_COUNT', 15, 'config_desc_modules.threadList.HIGHLIGHT_COUNT'),
];
