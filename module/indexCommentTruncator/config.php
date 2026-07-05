<?php
/**
 * Config schema for the indexCommentTruncator module (namespace: modules.indexCommentTruncator.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Appearance & pagination" editor group.
 */

require_once __DIR__ . '/../../global/configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{intField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Index comment truncator',

	'CHARACTER_PREVIEW_LIMIT' => intField('Preview character limit', 2500, 'Max characters shown in an index comment preview.'),
	'LINE_PREVIEW_LIMIT' => intField('Preview line limit', 10, 'Max lines shown in an index comment preview.'),
];
