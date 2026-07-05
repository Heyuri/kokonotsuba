<?php
/**
 * Config schema for the countryFlags module (namespace: modules.countryFlags.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Country flags',

	'FLAG_MODE' => intField('Country flag mode', 1, '1 = hide flags on posts with "flag" in the email field, 2 = show them.'),
];
