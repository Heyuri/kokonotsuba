<?php
/**
 * Config schema for the dice module (namespace: modules.dice.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Posting" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Posting',
	'_module' => 'Dice',

	'DICE_AMOUNT_LIMIT' => intField('Dice amount limit', 30, 'Maximum number of dice per roll.'),
	'DICE_FACE_LIMIT' => intField('Dice face limit', 9999, 'Maximum number of faces per die.'),
	'EMAIL_DICE_ROLL' => boolField('Dice via email field', false, 'Allow rolling dice from the email field.'),
	'COMMENT_DICE_ROLL' => boolField('Dice via comment', true, 'Allow rolling dice from the comment.'),
];
