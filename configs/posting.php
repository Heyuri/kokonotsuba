<?php
/**
 * Posting form behaviour, comment limits, defaults and tags (core). Module posting settings
 * (dice, kaomoji, tegaki, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Posting',

	'ALWAYS_NOKO'        => boolField('config_label_ALWAYS_NOKO', false, 'config_desc_ALWAYS_NOKO'),
	'USE_SAGE_CHECKBOX'  => boolField('config_label_USE_SAGE_CHECKBOX', true, 'config_desc_USE_SAGE_CHECKBOX'),
	'USE_NOKO_CHECKBOX'  => boolField('config_label_USE_NOKO_CHECKBOX', true, 'config_desc_USE_NOKO_CHECKBOX'),
	'USE_DUMP_CHECKBOX'  => boolField('config_label_USE_DUMP_CHECKBOX', true, 'config_desc_USE_DUMP_CHECKBOX'),
	'THREAD_ATTACHMENT_REQUIRED' => boolField('config_label_THREAD_ATTACHMENT_REQUIRED', true, 'config_desc_THREAD_ATTACHMENT_REQUIRED'),

	'ALLOW_NONAME'       => intField('config_label_ALLOW_NONAME', 1, 'config_desc_ALLOW_NONAME'),
	'CLEAR_SAGE'         => boolField('config_label_CLEAR_SAGE', false, 'config_desc_CLEAR_SAGE'),
	'NOTICE_SAGE'        => boolField('config_label_NOTICE_SAGE', true, 'config_desc_NOTICE_SAGE'),
	'USE_QUOTESYSTEM'    => boolField('config_label_USE_QUOTESYSTEM', true, 'config_desc_USE_QUOTESYSTEM'),
	'USE_CATEGORY'       => boolField('config_label_USE_CATEGORY', false, 'config_desc_USE_CATEGORY'),

	'COMM_MAX'           => intField('config_label_COMM_MAX', 5000, 'config_desc_COMM_MAX'),
	'INPUT_MAX'          => intField('config_label_INPUT_MAX', 100, 'config_desc_INPUT_MAX'),
	'BR_CHECK'           => intField('config_label_BR_CHECK', 0, 'config_desc_BR_CHECK'),

	'DEFAULT_NOTITLE'    => stringField('config_label_DEFAULT_NOTITLE', '', 'config_desc_DEFAULT_NOTITLE'),
	'DEFAULT_NONAME'     => stringField('config_label_DEFAULT_NONAME', 'Anonymous', 'config_desc_DEFAULT_NONAME'),
	'DEFAULT_NOCOMMENT'  => stringField('config_label_DEFAULT_NOCOMMENT', 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!', 'config_desc_DEFAULT_NOCOMMENT'),

	'ENABLE_TAGS'        => boolField('config_label_ENABLE_TAGS', false, 'config_desc_ENABLE_TAGS'),
	'FORCE_TAGS'         => boolField('config_label_FORCE_TAGS', false, 'config_desc_FORCE_TAGS'),
	'DEFAULT_TAG'        => stringField('config_label_DEFAULT_TAG', '?', 'config_desc_DEFAULT_TAG'),
	'TAGS'               => arrayField('config_label_TAGS', [
		'G'  => 'Games',
		'OC' => 'Original content',
		'A'  => 'Anime',
		'L'  => 'Loop',
		'E'  => 'Ero',
		'H'  => 'Heyuri',
		'?'  => 'Other',
	], 'config_desc_TAGS'),
];
