<?php
/**
 * Miscellaneous board settings: time zone, webhooks, logging, sessions, fortunes (core).
 * Module-specific settings are declared by their own module and fold into their groups.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Miscellaneous',

	'TIME_ZONE'                 => stringField('Time zone', '0', 'config_desc_TIME_ZONE'),
	'TRUST_HTTP_X_FORWARDED_FOR'=> boolField('Trust X-Forwarded-For', false, 'config_desc_TRUST_HTTP_X_FORWARDED_FOR'),

	'DISCORD_WH' => stringField('Discord webhook', '', 'config_desc_DISCORD_WH'),
	'IRC_WH'     => stringField('IRC webhook', '', 'config_desc_IRC_WH'),

	'ACTIONLOG_MAX_PER_PAGE' => intField('Action log per page', 50, 'config_desc_ACTIONLOG_MAX_PER_PAGE'),
	'STAFF_LOGIN_TIMEOUT'    => intField('Staff login timeout (s)', 86400, 'config_desc_STAFF_LOGIN_TIMEOUT'),
	'SYSTEMCHAN_NAME'        => stringField('System user name', 'System-chan', 'config_desc_SYSTEMCHAN_NAME'),

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
	], 'config_desc_FORTUNES'),
];
