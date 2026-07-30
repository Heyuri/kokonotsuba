<?php
/**
 * Config schema for the countryFlags module (namespace: modules.countryFlags.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Country flags',

	'FLAG_MODE' => intField('config_label_modules.countryFlags.FLAG_MODE', 1, 'config_desc_modules.countryFlags.FLAG_MODE'),
];
