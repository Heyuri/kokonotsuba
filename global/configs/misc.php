<?php
/**
 * Miscellaneous board settings: time zone, webhooks, logging, sessions, fortunes.
 * Defaults preserve the historical board defaults.
 */

return [
	'_group' => 'Miscellaneous',

	'TIME_ZONE'                 => ['default' => '0', 'type' => 'string', 'label' => 'Time zone', 'desc' => 'Offset from UTC, e.g. "-4" for New York or "9" for Japan.'],
	'TRUST_HTTP_X_FORWARDED_FOR'=> ['default' => 0, 'type' => 'int', 'label' => 'Trust X-Forwarded-For', 'desc' => 'Use HTTP_X_FORWARDED_FOR to find the real IP behind a proxy (headers can be forged).'],

	'DISCORD_WH' => ['default' => '', 'type' => 'string', 'label' => 'Discord webhook', 'desc' => 'Webhook URL for post notifications.'],
	'IRC_WH'     => ['default' => '', 'type' => 'string', 'label' => 'IRC webhook', 'desc' => 'Webhook URL for post notifications.'],

	'ACTIONLOG_MAX_PER_PAGE' => ['default' => 50, 'type' => 'int', 'label' => 'Action log per page', 'desc' => 'Number of action-log entries shown per page.'],
	'STAFF_LOGIN_TIMEOUT'    => ['default' => 86400, 'type' => 'int', 'label' => 'Staff login timeout (s)', 'desc' => 'Inactivity allowed before a staff user is logged out. Must not exceed session.gc_maxlifetime.'],
	'SYSTEMCHAN_NAME'        => ['default' => 'System-chan', 'type' => 'string', 'label' => 'System user name', 'desc' => 'Name of the system role/user.'],

	// private messages
	'ModuleSettings.APPEND_TRIP_PM_BUTTON_TO_POST' => ['default' => false, 'type' => 'bool', 'label' => 'Append PM button to posts', 'desc' => 'Show a private-message button next to tripcoded posts.'],

	'FORTUNES' => [
		'default' => [
			'Your true waifu will reveal herself',
			'Only time will tell',
			'Dark times are to come',
			'Your harem is only just begining',
			'You have cancer',
			'You have aids',
			'Tomo will strangle you in your sleep',
			'You just lost the game',
			'We don\'t know what happens next',
			'mah pen0z is bigger than uurz',
			'LOLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOLLOL',
			'Bad luck',
			'Average luck',
			'Good luck',
			'Godly luck',
			'Very bad luck',
			'ｷﾀ━━━━━━(ﾟ∀ﾟ)━━━━━━ !!!!',
			'（　´_ゝ`）ﾌｰﾝ',
		],
		'type'  => 'array',
		'label' => 'Fortunes',
		'desc'  => 'JSON array of fortunes selected at random by the fortune function.',
	],
];
