<?php
/**
 * Config schema for the blotter module (namespace: modules.blotter.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Blotter',

	'BLOTTER_PREVIEW_AMOUNT' => intField('Blotter preview amount', 5, 'Number of blotter entries previewed on the index and thread view.'),
];
