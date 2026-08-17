<?php
/**
 * Config schema for the notes module (namespace: modules.notes.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Staff notes',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.leaveNote' => boolField('config_label_modules.notes.PostMenu.leaveNote', true),
];
