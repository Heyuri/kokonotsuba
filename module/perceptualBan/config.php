<?php
/**
 * Config schema for the perceptualBan module (namespace: modules.perceptualBan.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Perceptual ban',

	'HAMMING_THRESHOLD' => intField('Hamming threshold', 10, 'Max Hamming distance for a perceptual hash match.'),
];
