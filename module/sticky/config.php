<?php
/**
 * Config schema for the sticky module (namespace: modules.sticky.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Sticky',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.sticky' => boolField('config_label_modules.sticky.PostMenu.sticky', true),
];
