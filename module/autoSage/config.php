<?php
/**
 * Config schema for the autoSage module (namespace: modules.autoSage.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Autosage',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.autosage' => boolField('config_label_modules.autoSage.PostMenu.autosage', true),
];
