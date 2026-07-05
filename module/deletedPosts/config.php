<?php
/**
 * Config schema for the deletedPosts module (namespace: modules.deletedPosts.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, templateField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Deleted posts',

	'DELETED_POSTS_TEMPLATE' => templateField('Deleted posts template', 'kokoimg', 'Template used to render deleted posts.'),
	'PRUNE_TIME' => intField('Deleted posts prune time (h)', 336, 'How long deleted-post records are retained, in hours.'),
];
