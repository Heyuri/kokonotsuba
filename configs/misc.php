<?php
/**
 * Miscellaneous board settings: time zone, webhooks, logging, sessions, fortunes (core).
 * Module-specific settings are declared by their own module and fold into their groups.
 */

require_once __DIR__ . '/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, arrayField};

return [
	'_group' => 'Miscellaneous',

	'TIME_ZONE'                 => stringField('config_label_TIME_ZONE', '0', 'config_desc_TIME_ZONE'),
	'TRUST_HTTP_X_FORWARDED_FOR'=> boolField('config_label_TRUST_HTTP_X_FORWARDED_FOR', false, 'config_desc_TRUST_HTTP_X_FORWARDED_FOR'),

	'DISCORD_WH' => stringField('config_label_DISCORD_WH', '', 'config_desc_DISCORD_WH'),
	'IRC_WH'     => stringField('config_label_IRC_WH', '', 'config_desc_IRC_WH'),

	'ACTIONLOG_MAX_PER_PAGE' => intField('config_label_ACTIONLOG_MAX_PER_PAGE', 50, 'config_desc_ACTIONLOG_MAX_PER_PAGE'),
	'STAFF_LOGIN_TIMEOUT'    => intField('config_label_STAFF_LOGIN_TIMEOUT', 86400, 'config_desc_STAFF_LOGIN_TIMEOUT'),
	'SYSTEMCHAN_NAME'        => stringField('config_label_SYSTEMCHAN_NAME', 'System-chan', 'config_desc_SYSTEMCHAN_NAME'),

	'FORTUNES' => arrayField('config_label_FORTUNES', [
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
