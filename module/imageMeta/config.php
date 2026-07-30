<?php
/**
 * Config schema for the imageMeta module (namespace: modules.imageMeta.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Image metadata',

	'EXIF_DATA_VIEWER' => boolField('config_label_modules.imageMeta.EXIF_DATA_VIEWER', false, 'config_desc_modules.imageMeta.EXIF_DATA_VIEWER'),
	'IMG_OPS' => boolField('config_label_modules.imageMeta.IMG_OPS', true, 'config_desc_modules.imageMeta.IMG_OPS'),
	'IQDB' => boolField('config_label_modules.imageMeta.IQDB', false, 'config_desc_modules.imageMeta.IQDB'),
	'SWFCHAN' => boolField('config_label_modules.imageMeta.SWFCHAN', true, 'config_desc_modules.imageMeta.SWFCHAN'),
];
