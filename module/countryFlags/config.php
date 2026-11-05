<?php
/**
 * Config schema for the countryFlags module (namespace: modules.countryFlags.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, selectField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Country flags',

	'FLAG_MODE' => selectField('config_label_modules.countryFlags.FLAG_MODE', 1, [
		1 => 'config_option_modules.countryFlags.FLAG_MODE.1',
		2 => 'config_option_modules.countryFlags.FLAG_MODE.2',
	], 'config_desc_modules.countryFlags.FLAG_MODE'),
	'DISPLAYFLAG_TOGGLE' => boolField('config_label_modules.countryFlags.DISPLAYFLAG_TOGGLE', true, 'config_desc_modules.countryFlags.DISPLAYFLAG_TOGGLE'),
];
