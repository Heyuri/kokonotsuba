<?php
/**
 * Page layout, templates and pagination (core). Module display settings (thread list, search,
 * index truncator, additional info, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField, templateField};

return [
	'_group' => 'Appearance & pagination',

	'HOME'               => stringField('Home link target', 'index.html', 'config_desc_HOME'),
	'FOOTTEXT'           => textField('Footer text', '', 'config_desc_FOOTTEXT'),
	'REF_URL'            => stringField('Referrer URL prefix', '', 'config_desc_REF_URL'),

	'TEMPLATE_FILE'       => templateField('Index template', 'kokoimg', 'config_desc_TEMPLATE_FILE'),
	'REPLY_TEMPLATE_FILE' => templateField('Reply template', 'kokoimg', 'config_desc_REPLY_TEMPLATE_FILE'),

	'TOP_THREAD_PAGER'   => boolField('Top thread pager', false, 'config_desc_TOP_THREAD_PAGER'),
	'RENDER_REPLY_NUMBER'=> boolField('Show reply numbers', true, 'config_desc_RENDER_REPLY_NUMBER'),
	'REPLIES_PER_PAGE'   => intField('Replies per thread page', 200, 'config_desc_REPLIES_PER_PAGE'),

	'PAGE_DEF'           => intField('Threads per page', 15, 'config_desc_PAGE_DEF'),
	'ADMIN_PAGE_DEF'     => intField('Admin replies per page', 100, 'config_desc_ADMIN_PAGE_DEF'),
	'RE_DEF'             => intField('Replies shown on index', 5, 'config_desc_RE_DEF'),
	'RE_PAGE_DEF'        => intField('Replies shown on thread', 1000, 'config_desc_RE_PAGE_DEF'),
	'MAX_RES'            => intField('Replies before auto-sage', 1000, 'config_desc_MAX_RES'),
	'MAX_THREAD_AMOUNT'  => intField('Max threads per board', 150, 'config_desc_MAX_THREAD_AMOUNT'),
	'MAX_AGE_TIME'       => intField('Age reply window (h)', 0, 'config_desc_MAX_AGE_TIME'),

	'STATIC_HTML_UNTIL'  => intField('Static HTML pages', 10, 'config_desc_STATIC_HTML_UNTIL', min: -1),
	'GZIP_COMPRESS_LEVEL'=> intField('Gzip level', 0, 'config_desc_GZIP_COMPRESS_LEVEL'),
	'MINIFY_HTML'        => boolField('Minify HTML', false, 'config_desc_MINIFY_HTML'),
	'AUTO_LINK'          => boolField('Auto-link URLs', true, 'config_desc_AUTO_LINK'),
];
