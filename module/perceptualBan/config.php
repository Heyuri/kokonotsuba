<?php
/**
 * Config schema for the perceptualBan module (namespace: modules.perceptualBan.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Perceptual ban',

	'HAMMING_THRESHOLD' => intField('config_label_modules.perceptualBan.HAMMING_THRESHOLD', 10, 'config_desc_modules.perceptualBan.HAMMING_THRESHOLD'),

	// menu entries this module adds, see widgetMenuPolicy
	'AttachmentMenu.BanFile'       => boolField('config_label_modules.perceptualBan.AttachmentMenu.BanFile', true),
	'AttachmentMenu.BanDeleteFile' => boolField('config_label_modules.perceptualBan.AttachmentMenu.BanDeleteFile', true),
];
