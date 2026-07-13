<?php
/**
 * Config schema for the soudane module (namespace: modules.soudane.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Soudane',

	'ENABLE_YEAH' => boolField('Enable "yeah" votes', true, 'config_desc_modules.soudane.ENABLE_YEAH'),
	'ENABLE_NOPE' => boolField('Enable "nope" votes', false, 'config_desc_modules.soudane.ENABLE_NOPE'),
	'ENABLE_SCORE' => boolField('Enable score', false, 'config_desc_modules.soudane.ENABLE_SCORE'),
	'SHOW_SCORE_ONLY' => boolField('Show score only', false, 'config_desc_modules.soudane.SHOW_SCORE_ONLY'),
];
