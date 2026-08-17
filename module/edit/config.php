<?php
/**
 * Config schema for the edit module (namespace: modules.edit.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Post editing',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.editPost' => boolField('config_label_modules.edit.PostMenu.editPost', true),
];
