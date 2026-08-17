<?php
/**
 * Config schema for the fileBan module (namespace: modules.fileBan.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'File ban',

	// menu entries this module adds, see widgetMenuPolicy
	'AttachmentMenu.BanFile'       => boolField('config_label_modules.fileBan.AttachmentMenu.BanFile', true),
	'AttachmentMenu.BanDeleteFile' => boolField('config_label_modules.fileBan.AttachmentMenu.BanDeleteFile', true),
];
