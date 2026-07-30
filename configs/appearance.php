<?php
/**
 * Page layout, templates and pagination (core). Module display settings (thread list, search,
 * index truncator, additional info, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField, templateField};

return [
	'_group' => 'Appearance & pagination',

	'HOME'               => stringField('config_label_HOME', 'index.html', 'config_desc_HOME'),
	'FOOTTEXT'           => textField('config_label_FOOTTEXT', '', 'config_desc_FOOTTEXT'),
	'REF_URL'            => stringField('config_label_REF_URL', '', 'config_desc_REF_URL'),

	'TEMPLATE_FILE'       => templateField('config_label_TEMPLATE_FILE', 'kokoimg', 'config_desc_TEMPLATE_FILE'),
	'REPLY_TEMPLATE_FILE' => templateField('config_label_REPLY_TEMPLATE_FILE', 'kokoimg', 'config_desc_REPLY_TEMPLATE_FILE'),

	'TOP_THREAD_PAGER'   => boolField('config_label_TOP_THREAD_PAGER', false, 'config_desc_TOP_THREAD_PAGER'),
	'RENDER_REPLY_NUMBER'=> boolField('config_label_RENDER_REPLY_NUMBER', true, 'config_desc_RENDER_REPLY_NUMBER'),
	'REPLIES_PER_PAGE'   => intField('config_label_REPLIES_PER_PAGE', 200, 'config_desc_REPLIES_PER_PAGE'),

	'PAGE_DEF'           => intField('config_label_PAGE_DEF', 15, 'config_desc_PAGE_DEF'),
	'ADMIN_PAGE_DEF'     => intField('config_label_ADMIN_PAGE_DEF', 100, 'config_desc_ADMIN_PAGE_DEF'),
	'RE_DEF'             => intField('config_label_RE_DEF', 5, 'config_desc_RE_DEF'),
	'RE_PAGE_DEF'        => intField('config_label_RE_PAGE_DEF', 1000, 'config_desc_RE_PAGE_DEF'),
	'MAX_RES'            => intField('config_label_MAX_RES', 1000, 'config_desc_MAX_RES'),
	'MAX_THREAD_AMOUNT'  => intField('config_label_MAX_THREAD_AMOUNT', 150, 'config_desc_MAX_THREAD_AMOUNT'),
	'MAX_AGE_TIME'       => intField('config_label_MAX_AGE_TIME', 0, 'config_desc_MAX_AGE_TIME'),

	'STATIC_HTML_UNTIL'  => intField('config_label_STATIC_HTML_UNTIL', 10, 'config_desc_STATIC_HTML_UNTIL', min: -1),
	'GZIP_COMPRESS_LEVEL'=> intField('config_label_GZIP_COMPRESS_LEVEL', 0, 'config_desc_GZIP_COMPRESS_LEVEL'),
	'MINIFY_HTML'        => boolField('config_label_MINIFY_HTML', false, 'config_desc_MINIFY_HTML'),
	'AUTO_LINK'          => boolField('config_label_AUTO_LINK', true, 'config_desc_AUTO_LINK'),
];
