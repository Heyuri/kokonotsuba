<?php
/**
 * Config schema for the tegaki module (namespace: modules.tegaki.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Posting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\templateField;

return [
	'_group'  => 'Posting',
	'_module' => 'Tegaki',

	'TEGAKI_TEMPLATE' => templateField('Tegaki template', 'tegaki', 'config_desc_modules.tegaki.TEGAKI_TEMPLATE'),
];
