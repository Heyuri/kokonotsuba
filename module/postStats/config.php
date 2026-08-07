<?php
/**
 * Config schema for the post statistics module (namespace: modules.postStats.*).
 * Read via $this->getModuleConfig('KEY').
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group'  => 'Appearance & pagination',
	'_module' => 'Post statistics',

	'SHOW_SITE_WIDE' => boolField('config_label_modules.postStats.SHOW_SITE_WIDE', true, 'config_desc_modules.postStats.SHOW_SITE_WIDE'),
	'SHOW_BOARD_TABLE' => boolField('config_label_modules.postStats.SHOW_BOARD_TABLE', true, 'config_desc_modules.postStats.SHOW_BOARD_TABLE'),
	'DEFAULT_RANGE_DAYS' => intField('config_label_modules.postStats.DEFAULT_RANGE_DAYS', 30, 'config_desc_modules.postStats.DEFAULT_RANGE_DAYS'),
	'MAX_BARS' => intField('config_label_modules.postStats.MAX_BARS', 120, 'config_desc_modules.postStats.MAX_BARS'),
];
