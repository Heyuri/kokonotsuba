<?php
/**
 * Config schema for the spoiler module (namespace: modules.spoiler.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Uploads" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Uploads',
	'_module' => 'Spoiler',

	'SPOILER_THUMB_W' => intField('config_label_modules.spoiler.SPOILER_THUMB_W', 255, 'config_desc_modules.spoiler.SPOILER_THUMB_W'),
	'SPOILER_THUMB_H' => intField('config_label_modules.spoiler.SPOILER_THUMB_H', 255, 'config_desc_modules.spoiler.SPOILER_THUMB_H'),
];
