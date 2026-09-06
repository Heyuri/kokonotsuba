<?php
/**
 * Config schema for the adminDel module (namespace: modules.adminDel.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Janitor / delete',

	'JANIMUTE_LENGTH' => intField('config_label_modules.adminDel.JANIMUTE_LENGTH', 20, 'config_desc_modules.adminDel.JANIMUTE_LENGTH'),
	'JANIMUTE_REASON' => stringField('config_label_modules.adminDel.JANIMUTE_REASON', 'You have been muted temporarily!', 'config_desc_modules.adminDel.JANIMUTE_REASON'),

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.delete'           => boolField('config_label_modules.adminDel.PostMenu.delete', true),
	'PostMenu.mute'             => boolField('config_label_modules.adminDel.PostMenu.mute', true),
	'AttachmentMenu.deleteFile' => boolField('config_label_modules.adminDel.AttachmentMenu.deleteFile', true),
];
