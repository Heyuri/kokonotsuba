<?php
/**
 * Config schema for the displayIp module (namespace: modules.displayIp.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Display IP',

	'IPTOGGLE' => intField('config_label_modules.displayIp.IPTOGGLE', 1, 'config_desc_modules.displayIp.IPTOGGLE'),
];
