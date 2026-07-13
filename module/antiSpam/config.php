<?php
/**
 * Config schema for the antiSpam module (namespace: modules.antiSpam.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Anti-spam',

	'FILTER_BAN_TIME' => intField('Filter ban time (h)', 24, 'config_desc_modules.antiSpam.FILTER_BAN_TIME'),
];
