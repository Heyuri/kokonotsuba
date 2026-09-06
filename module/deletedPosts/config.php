<?php
/**
 * Config schema for the deletedPosts module (namespace: modules.deletedPosts.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, templateField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Deleted posts',

	'DELETED_POSTS_TEMPLATE' => templateField('config_label_modules.deletedPosts.DELETED_POSTS_TEMPLATE', 'kokoimg', 'config_desc_modules.deletedPosts.DELETED_POSTS_TEMPLATE'),
	'PRUNE_TIME' => intField('config_label_modules.deletedPosts.PRUNE_TIME', 336, 'config_desc_modules.deletedPosts.PRUNE_TIME'),

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.viewDeletedPost'             => boolField('config_label_modules.deletedPosts.PostMenu.viewDeletedPost', true),
	'PostMenu.restoreDeletedPost'          => boolField('config_label_modules.deletedPosts.PostMenu.restoreDeletedPost', true),
	'PostMenu.purgeDeletedPost'            => boolField('config_label_modules.deletedPosts.PostMenu.purgeDeletedPost', true),
	'AttachmentMenu.viewDeletedAttachment' => boolField('config_label_modules.deletedPosts.AttachmentMenu.viewDeletedAttachment', true),
	'AttachmentMenu.restoreDeletedFile'    => boolField('config_label_modules.deletedPosts.AttachmentMenu.restoreDeletedFile', true),
	'AttachmentMenu.purgeDeletedFile'      => boolField('config_label_modules.deletedPosts.AttachmentMenu.purgeDeletedFile', true),
];
