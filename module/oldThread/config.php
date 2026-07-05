<?php
/**
 * Config schema for the oldThread module (namespace: modules.oldThread.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Flooding & rate limits" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group'  => 'Flooding & rate limits',
	'_module' => 'Old thread',

	'THREAD_REPLY_TIME_LIMIT' => intField('Thread reply time limit (h)', 0, 'Maximum thread age (hours) allowed for replies (0 = off).'),
];
