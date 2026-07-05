<?php
/**
 * Config schema for the readOnly module (namespace: modules.readOnly.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Read-only',

	'ALLOW_REPLY' => boolField('Allow replies when read-only', false, 'Allow replies but disallow new threads when the board is read-only.'),
];
