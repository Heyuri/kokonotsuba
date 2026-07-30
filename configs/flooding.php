<?php
/**
 * Flooding and rate-limit controls (core). Module-specific limits are declared by their own
 * module (e.g. module/antiFlood/config.php) and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group' => 'Flooding & rate limits',

	'RENZOKU'  => intField('config_label_RENZOKU', 0, 'config_desc_RENZOKU'),
	'RENZOKU2' => intField('config_label_RENZOKU2', 0, 'config_desc_RENZOKU2'),
];
