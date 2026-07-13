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

	'DICE_AMOUNT_LIMIT' => intField('Dice amount limit', 30, 'config_desc_modules.dice.DICE_AMOUNT_LIMIT'),
	'DICE_FACE_LIMIT' => intField('Dice face limit', 9999, 'config_desc_modules.dice.DICE_FACE_LIMIT'),
	'EMAIL_DICE_ROLL' => boolField('Dice via email field', false, 'config_desc_modules.dice.EMAIL_DICE_ROLL'),
	'COMMENT_DICE_ROLL' => boolField('Dice via comment', true, 'config_desc_modules.dice.COMMENT_DICE_ROLL'),
];
