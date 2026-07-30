<?php
/**
 * Config schema for the blotter module (namespace: modules.blotter.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Blotter',

	'BLOTTER_PREVIEW_AMOUNT' => intField('config_label_modules.blotter.BLOTTER_PREVIEW_AMOUNT', 5, 'config_desc_modules.blotter.BLOTTER_PREVIEW_AMOUNT'),
];
