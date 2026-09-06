<?php
/**
 * Config schema for the lockThread module (namespace: modules.lockThread.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Lock thread',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.lock' => boolField('config_label_modules.lockThread.PostMenu.lock', true),
];
