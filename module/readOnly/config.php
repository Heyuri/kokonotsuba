<?php
/**
 * Config schema for the readOnly module (namespace: modules.readOnly.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Read-only',

	'ALLOW_REPLY' => boolField('config_label_modules.readOnly.ALLOW_REPLY', false, 'config_desc_modules.readOnly.ALLOW_REPLY'),
];
