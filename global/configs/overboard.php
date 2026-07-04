<?php
/**
 * Overboard, ads, full banners and the online counter.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Overboard, ads & banners',

	'OVERBOARD_TITLE'           => ['default' => 'Overboard', 'type' => 'string', 'label' => 'Overboard title', 'desc' => 'Title of the overboard.'],
	'OVERBOARD_SUBTITLE'        => ['default' => 'Posts from all koko boards', 'type' => 'string', 'label' => 'Overboard subtitle', 'desc' => 'Subtitle of the overboard.'],
	'OVERBOARD_SUB_HEADER_HTML' => ['default' => '', 'type' => 'text', 'label' => 'Overboard sub-header HTML', 'desc' => 'HTML shown above the overboard filter box.'],
	'OVERBOARD_THREADS_PER_PAGE'=> ['default' => 20, 'type' => 'int', 'label' => 'Overboard threads per page', 'desc' => 'How many threads per overboard page.'],
	'ADMINBAR_OVERBOARD_BUTTON' => ['default' => true, 'type' => 'bool', 'label' => 'Overboard admin-bar button', 'desc' => 'Show an [Overboard] link on the admin bar.'],
	'CONTACT_URL'               => ['default' => '', 'type' => 'string', 'label' => 'Contact URL', 'desc' => 'Link shown as [Contact] on the admin bar (empty = hidden).'],

	// full banner
	'ModuleSettings.SHOW_TOP_AD'                => ['default' => true, 'type' => 'bool', 'label' => 'Show top full banner', 'desc' => 'Show the top full-banner ad.'],
	'ModuleSettings.SHOW_BOTTOM_AD'             => ['default' => true, 'type' => 'bool', 'label' => 'Show bottom full banner', 'desc' => 'Show the bottom full-banner ad.'],
	'ModuleSettings.FULLBANNER_SUBMISSION_COOLDOWN' => ['default' => 300, 'type' => 'int', 'label' => 'Banner submission cooldown (s)', 'desc' => 'Seconds between banner submissions per IP.'],
	'ModuleSettings.FULLBANNER_REQUIRED_WIDTH'  => ['default' => 468, 'type' => 'int', 'label' => 'Banner required width', 'desc' => 'Required banner image width in pixels.'],
	'ModuleSettings.FULLBANNER_REQUIRED_HEIGHT' => ['default' => 60, 'type' => 'int', 'label' => 'Banner required height', 'desc' => 'Required banner image height in pixels.'],
	'ModuleSettings.FULLBANNER_MAX_FILE_SIZE'   => ['default' => 204800, 'type' => 'int', 'label' => 'Banner max file size (bytes)', 'desc' => 'Maximum banner file size in bytes.'],

	// ads
	'ModuleSettings.ADS_STICKY_ROTATE_SECONDS'  => ['default' => 45, 'type' => 'int', 'label' => 'Sticky ad rotate (s)', 'desc' => 'Seconds between sticky ad rotations.'],
	'ModuleSettings.ADS_INLINE_EVERY_N_THREADS' => ['default' => 4, 'type' => 'int', 'label' => 'Inline ad every N threads', 'desc' => 'Insert an inline ad after every N threads.'],
	'ModuleSettings.ADS_INLINE_COUNT'           => ['default' => 2, 'type' => 'int', 'label' => 'Inline ads per row', 'desc' => 'Number of ads shown side-by-side in each inline row (1-5).'],
	'ModuleSettings.ADS_POST_AD_EVERY_N_POSTS'  => ['default' => 15, 'type' => 'int', 'label' => 'Post ad every N posts', 'desc' => 'Insert a post-style ad after every N reply posts within a thread.'],
	'ModuleSettings.ADS_SLOT_DIMENSIONS' => [
		'default' => [
			'top'     => ['width' => 728, 'height' => 90],
			'mobile'  => ['width' => 300, 'height' => 250],
			'above'   => ['width' => 728, 'height' => 150],
			'below'   => ['width' => 728, 'height' => 150],
			'inline'  => ['width' => 728, 'height' => 150],
			'post_ad' => ['width' => 300, 'height' => 250],
		],
		'type'  => 'array',
		'label' => 'Ad slot dimensions',
		'desc'  => 'JSON object of slot name => {width, height}.',
	],

	// online counter
	'ModuleSettings.USER_COUNT_DAT_FILE' => ['default' => 'users.dat', 'type' => 'string', 'label' => 'Online counter data file', 'desc' => 'Filename used to track viewing IPs (stored in the board storage dir).'],
	'ModuleSettings.USER_COUNT_TIMEOUT'  => ['default' => 10, 'type' => 'int', 'label' => 'Online counter timeout (min)', 'desc' => 'How long an IP counts as online, in minutes.'],
];
