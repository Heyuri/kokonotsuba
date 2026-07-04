<?php
/**
 * Posting form behaviour, comment limits, defaults, tags and dice.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Posting',

	'ALWAYS_NOKO'        => ['default' => false, 'type' => 'bool', 'label' => 'Always noko', 'desc' => 'Redirect to the reply by default.'],
	'USE_SAGE_CHECKBOX'  => ['default' => true,  'type' => 'bool', 'label' => 'Show sage checkbox', 'desc' => 'Show the sage checkbox in the post form.'],
	'USE_NOKO_CHECKBOX'  => ['default' => true,  'type' => 'bool', 'label' => 'Show noko checkbox', 'desc' => 'Show the noko checkbox in the post form.'],
	'USE_DUMP_CHECKBOX'  => ['default' => true,  'type' => 'bool', 'label' => 'Show dump checkbox', 'desc' => 'Show the dump checkbox in the post form.'],
	'THREAD_ATTACHMENT_REQUIRED' => ['default' => true, 'type' => 'bool', 'label' => 'Require file for new threads', 'desc' => 'New threads must have a file attached.'],

	'ALLOW_NONAME'       => ['default' => 1, 'type' => 'int', 'label' => 'Allow no name', 'desc' => 'Allow posters to submit without a name (1 = yes, 0 = no).'],
	'CLEAR_SAGE'         => ['default' => 0, 'type' => 'int', 'label' => 'Disable sage', 'desc' => 'Disable sage if set to 1.'],
	'NOTICE_SAGE'        => ['default' => 1, 'type' => 'int', 'label' => 'Visible sage', 'desc' => 'Show a visible "SAGE!" notice (1 = on).'],
	'USE_QUOTESYSTEM'    => ['default' => 1, 'type' => 'int', 'label' => 'Enable quote links', 'desc' => 'Enable >>1234 quote links (1 = on).'],
	'USE_CATEGORY'       => ['default' => 0, 'type' => 'int', 'label' => 'Enable categories', 'desc' => 'Enable post categories (1 = on).'],

	'COMM_MAX'           => ['default' => 5000, 'type' => 'int', 'label' => 'Max comment length', 'desc' => 'Maximum number of characters in a comment.'],
	'INPUT_MAX'          => ['default' => 100,  'type' => 'int', 'label' => 'Max field length', 'desc' => 'Maximum length of non-message fields (name, subject, etc.).'],
	'BR_CHECK'           => ['default' => 0,    'type' => 'int', 'label' => 'Line limit', 'desc' => 'How many lines to show (0 = no limit).'],

	'DEFAULT_NOTITLE'    => ['default' => '',           'type' => 'string', 'label' => 'Default title', 'desc' => 'Title used when none is entered.'],
	'DEFAULT_NONAME'     => ['default' => 'Anonymous',  'type' => 'string', 'label' => 'Default name', 'desc' => 'Name used when none is entered.'],
	'DEFAULT_NOCOMMENT'  => ['default' => 'ｷﾀ━━━(ﾟ∀ﾟ)━━━!!', 'type' => 'string', 'label' => 'Default comment', 'desc' => 'Comment used when none is entered.'],

	'ENABLE_TAGS'        => ['default' => false, 'type' => 'bool', 'label' => 'Enable tags', 'desc' => 'Show post tags (requires TAGS defined).'],
	'FORCE_TAGS'         => ['default' => false, 'type' => 'bool', 'label' => 'Force tags', 'desc' => 'Require a tag for new threads.'],
	'DEFAULT_TAG'        => ['default' => '?',   'type' => 'string', 'label' => 'Default tag', 'desc' => 'Default tag key for new threads (a key of TAGS, or empty).'],
	'TAGS'               => [
		'default' => [
			'G'  => 'Games',
			'OC' => 'Original content',
			'A'  => 'Anime',
			'L'  => 'Loop',
			'E'  => 'Ero',
			'H'  => 'Heyuri',
			'?'  => 'Other',
		],
		'type'  => 'array',
		'label' => 'Tags',
		'desc'  => 'Post tags: JSON object of abbreviation (stored) => display name.',
	],

	// dice
	'ModuleSettings.DICE_AMOUNT_LIMIT' => ['default' => 30,   'type' => 'int',  'label' => 'Dice amount limit', 'desc' => 'Maximum number of dice per roll.'],
	'ModuleSettings.DICE_FACE_LIMIT'   => ['default' => 9999, 'type' => 'int',  'label' => 'Dice face limit', 'desc' => 'Maximum number of faces per die.'],
	'ModuleSettings.EMAIL_DICE_ROLL'   => ['default' => false,'type' => 'bool', 'label' => 'Dice via email field', 'desc' => 'Allow rolling dice from the email field.'],
	'ModuleSettings.COMMENT_DICE_ROLL' => ['default' => true, 'type' => 'bool', 'label' => 'Dice via comment', 'desc' => 'Allow rolling dice from the comment.'],

	// kaomoji palette shown in the post form
	'ModuleSettings.KAOMOJI' => [
		'default' => [
			'ヽ(´ー｀)ノ' => '[kao]ヽ(´ー｀)ノ[/kao]',
			'(;´Д`)' => '[kao](;´Д`)[/kao]',
			'ヽ(´∇`)ノ' => '[kao]ヽ(´∇`)ノ[/kao]',
			'(´人｀)' => '[kao](´人｀)[/kao]',
			'(＾Д^)' => '[kao](＾Д^)[/kao]',
			'(´ー`)' => '[kao](´ー`)[/kao]',
			'（ ´,_ゝ`）' => '[kao]（ ´,_ゝ`）[/kao]',
			'(´～`)' => '[kao](´～`)[/kao]',
			'(;ﾟДﾟ)' => '[kao](;ﾟДﾟ)[/kao]',
			'(;ﾟ∀ﾟ)' => '[kao](;ﾟ∀ﾟ)[/kao]',
			'┐(ﾟ～ﾟ)┌' => '[kao]┐(ﾟ～ﾟ)┌[/kao]',
			'ヽ(`Д´)ノ' => '[kao]ヽ(`Д´)ノ[/kao]',
			'( ´ω`)' => '[kao]( ´ω`)[/kao]',
			'(ﾟー｀)' => '[kao](ﾟー｀)[/kao]',
			'(・∀・)' => '[kao](・∀・)[/kao]',
			'（⌒∇⌒ゞ）' => '[kao]（⌒∇⌒ゞ）[/kao]',
			'(ﾟ血ﾟ#)' => '[kao](ﾟ血ﾟ#)[/kao]',
			'(ﾟｰﾟ)' => '[kao](ﾟｰﾟ)[/kao]',
			'(´￢`)' => '[kao](´￢`)[/kao]',
			'(´π｀)' => '[kao](´π｀)[/kao]',
			'ヽ(ﾟρﾟ)ノ' => '[kao]ヽ(ﾟρﾟ)ノ[/kao]',
			'Σ(;ﾟДﾟ)' => '[kao]Σ(;ﾟДﾟ)[/kao]',
			'Σ(ﾟдﾟ|||)' => '[kao]Σ(ﾟдﾟ|||)[/kao]',
			'ｷﾀ━━━(・∀・)━━━!!' => '[kao]ｷﾀ━━━(・∀・)━━━!![/kao]',
		],
		'type'  => 'array',
		'label' => 'Kaomoji',
		'desc'  => 'JSON object of display text => value inserted into the comment.',
	],
];
