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

	'THREADLIST_NUMBER' => intField('Thread list per page', 50, 'config_desc_modules.threadList.THREADLIST_NUMBER'),
	'FORCE_SUBJECT' => boolField('Force subject', true, 'config_desc_modules.threadList.FORCE_SUBJECT'),
	'SHOW_IN_MAIN' => boolField('Show thread list on main', true, 'config_desc_modules.threadList.SHOW_IN_MAIN'),
	'THREADLIST_NUMBER_IN_MAIN' => intField('Thread list on main count', 40, 'config_desc_modules.threadList.THREADLIST_NUMBER_IN_MAIN'),
	'SHOW_FORM' => boolField('Show thread-list delete form', false, 'config_desc_modules.threadList.SHOW_FORM'),
	'HIGHLIGHT_COUNT' => intField('Popular reply highlight', 15, 'config_desc_modules.threadList.HIGHLIGHT_COUNT'),
];
