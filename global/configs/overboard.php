<?php
/**
 * Overboard (core). Module settings for ads, full banners and the online counter are declared
 * by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField};

return [
	'_group' => 'Overboard',

	'OVERBOARD_TITLE'           => stringField('Overboard title', 'Overboard', 'Title of the overboard.'),
	'OVERBOARD_SUBTITLE'        => stringField('Overboard subtitle', 'Posts from all koko boards', 'Subtitle of the overboard.'),
	'OVERBOARD_SUB_HEADER_HTML' => textField('Overboard sub-header HTML', '', 'HTML shown above the overboard filter box.'),
	'OVERBOARD_THREADS_PER_PAGE'=> intField('Overboard threads per page', 20, 'How many threads per overboard page.'),
	'ADMINBAR_OVERBOARD_BUTTON' => boolField('Overboard admin-bar button', true, 'Show an [Overboard] link on the admin bar.'),
	'CONTACT_URL'               => stringField('Contact URL', '', 'Link shown as [Contact] on the admin bar (empty = hidden).'),
];
