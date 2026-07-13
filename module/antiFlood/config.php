<?php
/**
 * Config schema for the antiFlood module. Keys are declared under modules.antiFlood.* and read
 * via $this->getModuleConfig('KEY'). Folds into the "Flooding & rate limits" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group'  => 'Flooding & rate limits',
	'_module' => 'Anti-flood',

	'RENZOKU3'                        => intField('Seconds between new threads', 30, 'config_desc_modules.antiFlood.RENZOKU3'),
	'SAME_COMMENT_TIME_WINDOW'        => intField('Same-comment window (s)', 10, 'config_desc_modules.antiFlood.SAME_COMMENT_TIME_WINDOW'),
	'SAME_THREAD_COMMENT_TIME_WINDOW' => intField('Same OP-comment window (s)', 10, 'config_desc_modules.antiFlood.SAME_THREAD_COMMENT_TIME_WINDOW'),
	'ALLOWED_COMMENT_REPETITIONS'     => intField('Allowed comment repetitions', 5, 'config_desc_modules.antiFlood.ALLOWED_COMMENT_REPETITIONS'),
];
