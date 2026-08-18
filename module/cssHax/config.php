<?php
/**
 * Config schema for the cssHax module (namespace: modules.cssHax.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Css hax',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.cssHax' => boolField('config_label_modules.cssHax.PostMenu.cssHax', true),
];
