<?php
/**
 * Moderation and deletion (core). Module moderation settings (janitor mute, soudane, deleted
 * posts, read-only, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group' => 'Moderation',

	'BAN_CHECK'               => boolField('Ban check', true, 'Comprehensive ban-check function.'),
	'POST_DELETION_TIME_LIMIT'=> intField('Post deletion time limit (h)', 168, 'Time limit for users deleting their posts, in hours.'),
];
