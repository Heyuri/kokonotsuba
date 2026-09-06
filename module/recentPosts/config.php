<?php
/**
 * Config schema for the recentPosts module (namespace: modules.recentPosts.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField, templateField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Recent posts',

	'RECENT_POSTS_PER_PAGE' => intField('config_label_modules.recentPosts.RECENT_POSTS_PER_PAGE', 30, 'config_desc_modules.recentPosts.RECENT_POSTS_PER_PAGE'),
	'RECENT_POSTS_TEMPLATE' => templateField('config_label_modules.recentPosts.RECENT_POSTS_TEMPLATE', 'kokoimg', 'config_desc_modules.recentPosts.RECENT_POSTS_TEMPLATE'),
];
