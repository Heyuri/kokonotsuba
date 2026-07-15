<?php
/**
 * Config schema for the oldThread module (namespace: modules.oldThread.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Flooding & rate limits" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group'  => 'Flooding & rate limits',
	'_module' => 'Old thread',

	'THREAD_REPLY_TIME_LIMIT' => intField('config_label_modules.oldThread.THREAD_REPLY_TIME_LIMIT', 0, 'config_desc_modules.oldThread.THREAD_REPLY_TIME_LIMIT'),
];
