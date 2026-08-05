<?php
/**
 * Config schema for the staffAlerts module (namespace: modules.staffAlerts.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group'  => 'Moderation',
	'_module' => 'Staff alerts',

	'POLL_SECONDS' => intField('config_label_modules.staffAlerts.POLL_SECONDS', 60, 'config_desc_modules.staffAlerts.POLL_SECONDS', min: 10),
];
