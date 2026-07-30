<?php
/**
 * Config schema for the dice module (namespace: modules.dice.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Posting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Posting',
	'_module' => 'Dice',

	'DICE_AMOUNT_LIMIT' => intField('config_label_modules.dice.DICE_AMOUNT_LIMIT', 30, 'config_desc_modules.dice.DICE_AMOUNT_LIMIT'),
	'DICE_FACE_LIMIT' => intField('config_label_modules.dice.DICE_FACE_LIMIT', 9999, 'config_desc_modules.dice.DICE_FACE_LIMIT'),
	'EMAIL_DICE_ROLL' => boolField('config_label_modules.dice.EMAIL_DICE_ROLL', false, 'config_desc_modules.dice.EMAIL_DICE_ROLL'),
	'COMMENT_DICE_ROLL' => boolField('config_label_modules.dice.COMMENT_DICE_ROLL', true, 'config_desc_modules.dice.COMMENT_DICE_ROLL'),
];
