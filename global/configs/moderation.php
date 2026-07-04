<?php
/**
 * Moderation, deletion, bans, IDs and voting.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Moderation',

	'BAN_CHECK'               => ['default' => 1,   'type' => 'int', 'label' => 'Ban check', 'desc' => 'Comprehensive ban-check function (1 = on).'],
	'POST_DELETION_TIME_LIMIT'=> ['default' => 168, 'type' => 'int', 'label' => 'Post deletion time limit (h)', 'desc' => 'Time limit for users deleting their posts, in hours.'],

	// janitor mute (adminDel)
	'ModuleSettings.JANIMUTE_LENGTH' => ['default' => 20, 'type' => 'int', 'label' => 'Janitor mute length (min)', 'desc' => 'Janitor mute duration in minutes.'],
	'ModuleSettings.JANIMUTE_REASON' => ['default' => 'You have been muted temporarily!', 'type' => 'string', 'label' => 'Janitor mute reason', 'desc' => 'Reason shown for a janitor mute.'],

	// showip / posterID
	'ModuleSettings.IPTOGGLE' => ['default' => 1, 'type' => 'int', 'label' => 'IP display toggle', 'desc' => '1 = OPs toggle IP display, 2 = enabled for all posts.'],
	'ModuleSettings.DISP_ID'  => ['default' => false, 'type' => 'bool', 'label' => 'Always show poster ID', 'desc' => 'When poster IDs are enabled: false = OPs opt in via mail, true = always on.'],

	// deleted posts
	'ModuleSettings.DELETED_POSTS_TEMPLATE' => ['default' => 'kokoimg', 'type' => 'string', 'label' => 'Deleted posts template', 'desc' => 'Template used to render deleted posts.'],
	'ModuleSettings.PRUNE_TIME'             => ['default' => 336, 'type' => 'int', 'label' => 'Deleted posts prune time (h)', 'desc' => 'How long deleted-post records are retained, in hours.'],

	// read only
	'ModuleSettings.ALLOW_REPLY' => ['default' => false, 'type' => 'bool', 'label' => 'Allow replies when read-only', 'desc' => 'Allow replies but disallow new threads when the board is read-only.'],

	// soudane voting
	'ModuleSettings.ENABLE_YEAH'      => ['default' => true,  'type' => 'bool', 'label' => 'Enable "yeah" votes', 'desc' => 'Enable positive soudane votes.'],
	'ModuleSettings.ENABLE_NOPE'      => ['default' => false, 'type' => 'bool', 'label' => 'Enable "nope" votes', 'desc' => 'Enable negative soudane votes.'],
	'ModuleSettings.ENABLE_SCORE'     => ['default' => false, 'type' => 'bool', 'label' => 'Enable score', 'desc' => 'Enable a numeric vote score.'],
	'ModuleSettings.SHOW_SCORE_ONLY'  => ['default' => false, 'type' => 'bool', 'label' => 'Show score only', 'desc' => 'Show only the score rather than individual vote buttons.'],

	// blotter
	'ModuleSettings.BLOTTER_PREVIEW_AMOUNT' => ['default' => 5, 'type' => 'int', 'label' => 'Blotter preview amount', 'desc' => 'Number of blotter entries previewed on the index and thread view.'],
];
