<?php
/**
 * Config schema for the addInfo module (namespace: modules.addInfo.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{arrayField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Additional info',

	'ADD_INFO' => arrayField('Additional info lines', [
  0 => 'Read the <a href="//example.net/rules.html">rules</a> before you post.',
  1 => 'Read <a href="//example.net/faq.html">our FAQ</a> for any questions.',
  2 => 'Modify this by editing the additional-info setting.',
], 'config_desc_modules.addInfo.ADD_INFO'),
];
