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

	'ENABLE_YEAH' => boolField('config_label_modules.soudane.ENABLE_YEAH', true, 'config_desc_modules.soudane.ENABLE_YEAH'),
	'ENABLE_NOPE' => boolField('config_label_modules.soudane.ENABLE_NOPE', false, 'config_desc_modules.soudane.ENABLE_NOPE'),
	'ENABLE_SCORE' => boolField('config_label_modules.soudane.ENABLE_SCORE', false, 'config_desc_modules.soudane.ENABLE_SCORE'),
	'SHOW_SCORE_ONLY' => boolField('config_label_modules.soudane.SHOW_SCORE_ONLY', false, 'config_desc_modules.soudane.SHOW_SCORE_ONLY'),

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.viewVotes' => boolField('config_label_modules.soudane.PostMenu.viewVotes', true),
];
