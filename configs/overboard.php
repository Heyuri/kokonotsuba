<?php
/**
 * Overboard (core). Module settings for ads, full banners and the online counter are declared
 * by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField};

return [
	'_group' => 'Overboard',

	'OVERBOARD_TITLE'           => stringField('config_label_OVERBOARD_TITLE', 'Overboard', 'config_desc_OVERBOARD_TITLE'),
	'OVERBOARD_SUBTITLE'        => stringField('config_label_OVERBOARD_SUBTITLE', 'Posts from all koko boards', 'config_desc_OVERBOARD_SUBTITLE'),
	'OVERBOARD_SUB_HEADER_HTML' => textField('config_label_OVERBOARD_SUB_HEADER_HTML', '', 'config_desc_OVERBOARD_SUB_HEADER_HTML'),
	'OVERBOARD_THREADS_PER_PAGE'=> intField('config_label_OVERBOARD_THREADS_PER_PAGE', 20, 'config_desc_OVERBOARD_THREADS_PER_PAGE'),
	'ADMINBAR_OVERBOARD_BUTTON' => boolField('config_label_ADMINBAR_OVERBOARD_BUTTON', true, 'config_desc_ADMINBAR_OVERBOARD_BUTTON'),
	'CONTACT_URL'               => stringField('config_label_CONTACT_URL', '', 'config_desc_CONTACT_URL'),
];
