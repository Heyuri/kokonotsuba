<?php
/**
 * Config schema for the edit module (namespace: modules.edit.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Posting" editor group.
 *
 * Only the reader half is configured here. Which staff role may edit anyone's post is a role, so
 * it lives in $config['AuthLevels'] alongside every other capability: CAN_EDIT_POST, and so is
 * who may read a post's edit history: CAN_VIEW_POST_REVISIONS.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Posting',
	'_module' => 'Post editing',

	'ALLOW_USER_EDIT' => boolField('config_label_modules.edit.ALLOW_USER_EDIT', true, 'config_desc_modules.edit.ALLOW_USER_EDIT'),
	'USER_EDIT_TIME_LIMIT' => intField('config_label_modules.edit.USER_EDIT_TIME_LIMIT', 1, 'config_desc_modules.edit.USER_EDIT_TIME_LIMIT', min: 0),
	'ATTACHMENT_EDIT_TIME_LIMIT' => intField('config_label_modules.edit.ATTACHMENT_EDIT_TIME_LIMIT', 1, 'config_desc_modules.edit.ATTACHMENT_EDIT_TIME_LIMIT', min: 0),
];
