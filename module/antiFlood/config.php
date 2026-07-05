<?php
/**
 * Config schema for the antiFlood module. Keys are declared under modules.antiFlood.* and read
 * via $this->getModuleConfig('KEY'). Folds into the "Flooding & rate limits" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group'  => 'Flooding & rate limits',
	'_module' => 'Anti-flood',

	'RENZOKU3'                        => intField('Seconds between new threads', 30, 'Minimum wait before a poster can start another thread.'),
	'SAME_COMMENT_TIME_WINDOW'        => intField('Same-comment window (s)', 10, 'Seconds between posts that can share the same comment.'),
	'SAME_THREAD_COMMENT_TIME_WINDOW' => intField('Same OP-comment window (s)', 10, 'Seconds between OP posts that can share the same comment (0 = off).'),
	'ALLOWED_COMMENT_REPETITIONS'     => intField('Allowed comment repetitions', 5, 'How many identical comments are allowed in the window before older ones are pruned.'),
];
