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

	'SHOW_VISITOR_TOKEN' => boolField('config_label_modules.viewPosts.SHOW_VISITOR_TOKEN', true, 'config_desc_modules.viewPosts.SHOW_VISITOR_TOKEN'),
];
