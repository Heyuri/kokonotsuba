<?php
/**
 * Config schema for the displayId module (namespace: modules.displayId.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Display ID',

	'DISP_ID' => boolField('Always show poster ID', false, 'config_desc_modules.displayId.DISP_ID'),
];
