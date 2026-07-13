<?php
/**
 * Posting form behaviour, comment limits, defaults and tags (core). Module posting settings
 * (dice, kaomoji, tegaki, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Posting',

	'ALWAYS_NOKO'        => boolField('Always noko', false, 'config_desc_ALWAYS_NOKO'),
	'USE_SAGE_CHECKBOX'  => boolField('Show sage checkbox', true, 'config_desc_USE_SAGE_CHECKBOX'),
	'USE_NOKO_CHECKBOX'  => boolField('Show noko checkbox', true, 'config_desc_USE_NOKO_CHECKBOX'),
	'USE_DUMP_CHECKBOX'  => boolField('Show dump checkbox', true, 'config_desc_USE_DUMP_CHECKBOX'),
	'THREAD_ATTACHMENT_REQUIRED' => boolField('Require file for new threads', true, 'config_desc_THREAD_ATTACHMENT_REQUIRED'),

	'ALLOW_NONAME'       => intField('Allow no name', 1, 'config_desc_ALLOW_NONAME'),
	'CLEAR_SAGE'         => boolField('Disable sage', false, 'config_desc_CLEAR_SAGE'),
	'NOTICE_SAGE'        => boolField('Visible sage', true, 'config_desc_NOTICE_SAGE'),
	'USE_QUOTESYSTEM'    => boolField('Enable quote links', true, 'config_desc_USE_QUOTESYSTEM'),
	'USE_CATEGORY'       => boolField('Enable categories', false, 'config_desc_USE_CATEGORY'),

	'COMM_MAX'           => intField('Max comment length', 5000, 'config_desc_COMM_MAX'),
	'INPUT_MAX'          => intField('Max field length', 100, 'config_desc_INPUT_MAX'),
	'BR_CHECK'           => intField('Line limit', 0, 'config_desc_BR_CHECK'),

	'DEFAULT_NOTITLE'    => stringField('Default title', '', 'config_desc_DEFAULT_NOTITLE'),
	'DEFAULT_NONAME'     => stringField('Default name', 'Anonymous', 'config_desc_DEFAULT_NONAME'),
	'DEFAULT_NOCOMMENT'  => stringField('Default comment', 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!', 'config_desc_DEFAULT_NOCOMMENT'),

	'ENABLE_TAGS'        => boolField('Enable tags', false, 'config_desc_ENABLE_TAGS'),
	'FORCE_TAGS'         => boolField('Force tags', false, 'config_desc_FORCE_TAGS'),
	'DEFAULT_TAG'        => stringField('Default tag', '?', 'config_desc_DEFAULT_TAG'),
	'TAGS'               => arrayField('Tags', [
		'G'  => 'Games',
		'OC' => 'Original content',
		'A'  => 'Anime',
		'L'  => 'Loop',
		'E'  => 'Ero',
		'H'  => 'Heyuri',
		'?'  => 'Other',
	], 'config_desc_TAGS'),
];
