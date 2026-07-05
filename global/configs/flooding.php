<?php
/**
 * Flooding and rate-limit controls (core). Module-specific limits are declared by their own
 * module (e.g. module/antiFlood/config.php) and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\intField;

return [
	'_group' => 'Flooding & rate limits',

	'RENZOKU'  => intField('Post interval (s)', 0, 'Minimum seconds between posts (0 = off).'),
	'RENZOKU2' => intField('Image post interval (s)', 0, 'Minimum seconds between image posts (0 = off).'),
];
