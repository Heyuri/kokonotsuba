<?php
/**
 * Config schema for the animatedGif module (namespace: modules.animatedGif.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Uploads" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Uploads',
	'_module' => 'Animated GIF',

	'MAX_SIZE_FOR_ANIMATED_GIF' => intField('config_label_modules.animatedGif.MAX_SIZE_FOR_ANIMATED_GIF', 2000, 'config_desc_modules.animatedGif.MAX_SIZE_FOR_ANIMATED_GIF'),
];
