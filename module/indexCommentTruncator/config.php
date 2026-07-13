<?php
/**
 * Config schema for the indexCommentTruncator module (namespace: modules.indexCommentTruncator.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Index comment truncator',

	'CHARACTER_PREVIEW_LIMIT' => intField('Preview character limit', 2500, 'config_desc_modules.indexCommentTruncator.CHARACTER_PREVIEW_LIMIT'),
	'LINE_PREVIEW_LIMIT' => intField('Preview line limit', 10, 'config_desc_modules.indexCommentTruncator.LINE_PREVIEW_LIMIT'),
];
