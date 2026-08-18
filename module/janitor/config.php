<?php
/**
 * Config schema for the janitor module (namespace: modules.janitor.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Janitor',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.warn' => boolField('config_label_modules.janitor.PostMenu.warn', true),
];
