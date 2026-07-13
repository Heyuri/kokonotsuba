<?php
/**
 * Moderation and deletion (core). Module moderation settings (janitor mute, soudane, deleted
 * posts, read-only, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField};

return [
	'_group' => 'Moderation',

	'BAN_CHECK'               => boolField('Ban check', true, 'config_desc_BAN_CHECK'),
	'POST_DELETION_TIME_LIMIT'=> intField('Post deletion time limit (h)', 168, 'config_desc_POST_DELETION_TIME_LIMIT'),
];
