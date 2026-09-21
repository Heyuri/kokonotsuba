<?php
/**
 * Config schema for the debug bar module (namespace: modules.debugBar.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Miscellaneous" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\boolField;

return [
	'_group'  => 'Miscellaneous',
	'_module' => 'Debug bar',

	'PROFILER_STAFF_ONLY' => boolField('config_label_modules.debugBar.PROFILER_STAFF_ONLY', true, 'config_desc_modules.debugBar.PROFILER_STAFF_ONLY'),
];
