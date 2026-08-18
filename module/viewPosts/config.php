<?php
/**
 * Config schema for the viewPosts module (namespace: modules.viewPosts.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'View posts',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.viewPosts' => boolField('config_label_modules.viewPosts.PostMenu.viewPosts', true),
];
