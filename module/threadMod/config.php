<?php
/**
 * Config schema for the threadMod module (namespace: modules.threadMod.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 *
 * Which staff role may override an OP's decisions is a role, so it stays in
 * $config['AuthLevels'] (CAN_DELETE_POST).
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Thread moderation',

	'OWNER_CAPCODE_TEXT' => stringField('config_label_modules.threadMod.OWNER_CAPCODE_TEXT', 'OP', 'config_desc_modules.threadMod.OWNER_CAPCODE_TEXT'),
	'MOD_CAPCODE_TEXT' => stringField('config_label_modules.threadMod.MOD_CAPCODE_TEXT', 'Thread Mod', 'config_desc_modules.threadMod.MOD_CAPCODE_TEXT'),
	'MAX_DELETIONS_PER_THREAD' => intField('config_label_modules.threadMod.MAX_DELETIONS_PER_THREAD', 0, 'config_desc_modules.threadMod.MAX_DELETIONS_PER_THREAD', min: 0),
	'ALLOW_PASSWORD_OWNERSHIP' => boolField('config_label_modules.threadMod.ALLOW_PASSWORD_OWNERSHIP', true, 'config_desc_modules.threadMod.ALLOW_PASSWORD_OWNERSHIP'),

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.threadModUndo' => boolField('config_label_modules.threadMod.PostMenu.threadModUndo', true),
	'PostMenu.threadModUndoAll' => boolField('config_label_modules.threadMod.PostMenu.threadModUndoAll', true),
	'PostMenu.threadModStrip' => boolField('config_label_modules.threadMod.PostMenu.threadModStrip', true),
];
