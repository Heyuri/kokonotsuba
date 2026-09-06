<?php
/**
 * Config schema for the moveThread module (namespace: modules.moveThread.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Move thread',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.moveThread' => boolField('config_label_modules.moveThread.PostMenu.moveThread', true),
];
