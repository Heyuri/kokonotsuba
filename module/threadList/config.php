<?php
/**
 * Config schema for the threadList module (namespace: modules.threadList.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Thread list',

	'THREADLIST_NUMBER' => intField('Thread list per page', 50, 'Number of entries shown per thread-list page.'),
	'FORCE_SUBJECT' => boolField('Force subject', true, 'Require a subject for new threads.'),
	'SHOW_IN_MAIN' => boolField('Show thread list on main', true, 'Display the thread list on the main page.'),
	'THREADLIST_NUMBER_IN_MAIN' => intField('Thread list on main count', 40, 'Number of entries shown on the main page.'),
	'SHOW_FORM' => boolField('Show thread-list delete form', false, 'Display the delete form on the thread list.'),
	'HIGHLIGHT_COUNT' => intField('Popular reply highlight', 15, 'Reply count above which the count turns red (0 = off).'),
];
