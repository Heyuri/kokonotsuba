<?php
/**
 * Page layout, templates and pagination (core). Module display settings (thread list, search,
 * index truncator, additional info, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField, templateField};

return [
	'_group' => 'Appearance & pagination',

	'HOME'               => stringField('Home link target', 'index.html', 'What the [Home] button links to.'),
	'FOOTTEXT'           => textField('Footer text', '', 'HTML shown at the bottom of the page.'),
	'REF_URL'            => stringField('Referrer URL prefix', '', 'URL prefix for outbound links (e.g. https://jump.example.net).'),

	'TEMPLATE_FILE'       => templateField('Index template', 'kokoimg', 'Template directory for the index page.'),
	'REPLY_TEMPLATE_FILE' => templateField('Reply template', 'kokoimg', 'Template directory for the thread/reply page.'),

	'TOP_THREAD_PAGER'   => boolField('Top thread pager', false, 'Render a thread pager at the top of the thread.'),
	'RENDER_REPLY_NUMBER'=> boolField('Show reply numbers', true, 'Show the sequential reply number for each post within a thread.'),
	'REPLIES_PER_PAGE'   => intField('Replies per thread page', 200, 'Replies shown (excluding OP) per thread page.'),

	'PAGE_DEF'           => intField('Threads per page', 15, 'How many threads per index page.'),
	'ADMIN_PAGE_DEF'     => intField('Admin replies per page', 100, 'How many replies per page in the admin panel.'),
	'RE_DEF'             => intField('Replies shown on index', 5, 'Replies shown per thread on the index.'),
	'RE_PAGE_DEF'        => intField('Replies shown on thread', 1000, 'Replies shown on the thread page.'),
	'MAX_RES'            => intField('Replies before auto-sage', 1000, 'How many replies before a thread is auto-saged.'),
	'MAX_THREAD_AMOUNT'  => intField('Max threads per board', 150, 'Threads beyond this are pruned oldest-first.'),
	'MAX_AGE_TIME'       => intField('Age reply window (h)', 0, 'How long a thread accepts age replies, in hours (0 = always).'),

	'STATIC_HTML_UNTIL'  => intField('Static HTML pages', 10, 'How many index pages are statically generated (-1 = all, 0 = portal only).'),
	'GZIP_COMPRESS_LEVEL'=> intField('Gzip level', 0, 'Gzip compression level (1-9, 0 = off).'),
	'MINIFY_HTML'        => boolField('Minify HTML', false, 'Remove unnecessary whitespace from generated HTML.'),
	'AUTO_LINK'          => boolField('Auto-link URLs', true, 'Turn URLs in comments into links.'),
];
