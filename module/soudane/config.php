<?php
/**
 * Config schema for the soudane module (namespace: modules.soudane.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Soudane',

	'ENABLE_YEAH' => boolField('Enable "yeah" votes', true, 'Enable positive soudane votes.'),
	'ENABLE_NOPE' => boolField('Enable "nope" votes', false, 'Enable negative soudane votes.'),
	'ENABLE_SCORE' => boolField('Enable score', false, 'Enable a numeric vote score.'),
	'SHOW_SCORE_ONLY' => boolField('Show score only', false, 'Show only the score rather than individual vote buttons.'),
];
