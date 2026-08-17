<?php
/**
 * Config schema for the filter module (namespace: modules.filter.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Filter',

	// menu entries this module adds, see widgetMenuPolicy
	'PostMenu.hide'            => boolField('config_label_modules.filter.PostMenu.hide', true),
	'AttachmentMenu.hideImage' => boolField('config_label_modules.filter.AttachmentMenu.hideImage', true),
];
