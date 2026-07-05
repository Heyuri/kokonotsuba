<?php
/**
 * Config schema for the displayIp module (namespace: modules.displayIp.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Display IP',

	'IPTOGGLE' => intField('IP display toggle', 1, '1 = OPs toggle IP display, 2 = enabled for all posts.'),
];
