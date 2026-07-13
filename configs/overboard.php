<?php
/**
 * Overboard config defaults
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField};

return [
	'_group' => 'Overboard',

	'OVERBOARD_TITLE'           => stringField('Overboard title', 'Overboard', 'config_desc_OVERBOARD_TITLE'),
	'OVERBOARD_SUBTITLE'        => stringField('Overboard subtitle', 'Posts from all koko boards', 'config_desc_OVERBOARD_SUBTITLE'),
	'OVERBOARD_SUB_HEADER_HTML' => textField('Overboard sub-header HTML', '', 'config_desc_OVERBOARD_SUB_HEADER_HTML'),
	'OVERBOARD_THREADS_PER_PAGE'=> intField('Overboard threads per page', 20, 'config_desc_OVERBOARD_THREADS_PER_PAGE'),
	'ADMINBAR_OVERBOARD_BUTTON' => boolField('Overboard admin-bar button', true, 'config_desc_ADMINBAR_OVERBOARD_BUTTON'),
	'CONTACT_URL'               => stringField('Contact URL', '', 'config_desc_CONTACT_URL'),
];
