<?php
/**
 * Config schema for the imageMeta module (namespace: modules.imageMeta.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Content & formatting" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField};

return [
	'_group'  => 'Content & formatting',
	'_module' => 'Image metadata',

	'EXIF_DATA_VIEWER' => boolField('EXIF data viewer', false, 'Show an EXIF data viewer for images.'),
	'IMG_OPS' => boolField('ImgOps portal', true, 'ImgOps reverse-image-search portal.'),
	'IQDB' => boolField('IQDB portal', false, 'IQDB reverse-image-search portal.'),
	'SWFCHAN' => boolField('SWFchan archive', true, 'SWFchan archive link.'),
];
