<?php
/**
 * Miscellaneous board settings: time zone, webhooks, logging, sessions, fortunes (core).
 * Module-specific settings are declared by their own module and fold into their groups.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Miscellaneous',

	'TIME_ZONE'                 => stringField('Time zone', '0', 'Offset from UTC, e.g. "-4" for New York or "9" for Japan.'),
	'TRUST_HTTP_X_FORWARDED_FOR'=> boolField('Trust X-Forwarded-For', false, 'Use HTTP_X_FORWARDED_FOR to find the real IP behind a proxy (headers can be forged).'),

	'DISCORD_WH' => stringField('Discord webhook', '', 'Webhook URL for post notifications.'),
	'IRC_WH'     => stringField('IRC webhook', '', 'Webhook URL for post notifications.'),

	'ACTIONLOG_MAX_PER_PAGE' => intField('Action log per page', 50, 'Number of action-log entries shown per page.'),
	'STAFF_LOGIN_TIMEOUT'    => intField('Staff login timeout (s)', 86400, 'Inactivity allowed before a staff user is logged out. Must not exceed session.gc_maxlifetime.'),
	'SYSTEMCHAN_NAME'        => stringField('System user name', 'System-chan', 'Name of the system role/user.'),

	'FORTUNES' => arrayField('Fortunes', [
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
	], 'JSON array of fortunes selected at random by the fortune function.'),
];
