<?php
/**
 * Config schema for the deletedPosts module (namespace: modules.deletedPosts.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, templateField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Deleted posts',

	'DELETED_POSTS_TEMPLATE' => templateField('Deleted posts template', 'kokoimg', 'config_desc_modules.deletedPosts.DELETED_POSTS_TEMPLATE'),
	'PRUNE_TIME' => intField('Deleted posts prune time (h)', 336, 'config_desc_modules.deletedPosts.PRUNE_TIME'),
];
