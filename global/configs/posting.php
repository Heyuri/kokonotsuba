<?php
/**
 * Posting form behaviour, comment limits, defaults and tags (core). Module posting settings
 * (dice, kaomoji, tegaki, ...) are declared by their own module and fold into this group.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Posting',

	'ALWAYS_NOKO'        => boolField('Always noko', false, 'Redirect to the reply by default.'),
	'USE_SAGE_CHECKBOX'  => boolField('Show sage checkbox', true, 'Show the sage checkbox in the post form.'),
	'USE_NOKO_CHECKBOX'  => boolField('Show noko checkbox', true, 'Show the noko checkbox in the post form.'),
	'USE_DUMP_CHECKBOX'  => boolField('Show dump checkbox', true, 'Show the dump checkbox in the post form.'),
	'THREAD_ATTACHMENT_REQUIRED' => boolField('Require file for new threads', true, 'New threads must have a file attached.'),

	'ALLOW_NONAME'       => intField('Allow no name', 1, 'Allow posters to submit without a name (1 = yes, 0 = no).'),
	'CLEAR_SAGE'         => boolField('Disable sage', false, 'Disable sage entirely.'),
	'NOTICE_SAGE'        => boolField('Visible sage', true, 'Show a visible "SAGE!" notice.'),
	'USE_QUOTESYSTEM'    => boolField('Enable quote links', true, 'Enable >>1234 quote links.'),
	'USE_CATEGORY'       => boolField('Enable categories', false, 'Enable post categories.'),

	'COMM_MAX'           => intField('Max comment length', 5000, 'Maximum number of characters in a comment.'),
	'INPUT_MAX'          => intField('Max field length', 100, 'Maximum length of non-message fields (name, subject, etc.).'),
	'BR_CHECK'           => intField('Line limit', 0, 'How many lines to show (0 = no limit).'),

	'DEFAULT_NOTITLE'    => stringField('Default title', '', 'Title used when none is entered.'),
	'DEFAULT_NONAME'     => stringField('Default name', 'Anonymous', 'Name used when none is entered.'),
	'DEFAULT_NOCOMMENT'  => stringField('Default comment', 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!', 'Comment used when none is entered.'),

	'ENABLE_TAGS'        => boolField('Enable tags', false, 'Show post tags (requires TAGS defined).'),
	'FORCE_TAGS'         => boolField('Force tags', false, 'Require a tag for new threads.'),
	'DEFAULT_TAG'        => stringField('Default tag', '?', 'Default tag key for new threads (a key of TAGS, or empty).'),
	'TAGS'               => arrayField('Tags', [
		'G'  => 'Games',
		'OC' => 'Original content',
		'A'  => 'Anime',
		'L'  => 'Loop',
		'E'  => 'Ero',
		'H'  => 'Heyuri',
		'?'  => 'Other',
	], 'Post tags: JSON object of abbreviation (stored) => display name.'),
];
