<?php
/**
 * Config schema for the adminBan module (namespace: modules.adminBan.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Ban',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.ban' => boolField('config_label_modules.adminBan.PostMenu.ban', true),
];
