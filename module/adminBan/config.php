<?php
/**
 * Config schema for the adminBan module (namespace: modules.adminBan.*).
 * Read via $this->getModuleConfig('KEY'). Folds into the "Moderation" editor group.
 *
 * Who may file, lift and appeal-review bans is not configured here - those are roles, so they
 * live in $config['AuthLevels']: CAN_BAN, CAN_VIEW_BAN_APPEALS, CAN_ACTION_BAN_APPEAL.
 */

require_once __DIR__ . '/../../configs/_fieldTypes.php';

use function Kokonotsuba\config\fields\{boolField, intField, stringField, textField};

return [
	'_group'  => 'Moderation',
	'_module' => 'Bans',

	'DEFAULT_BAN_MESSAGE' => textField('config_label_modules.adminBan.DEFAULT_BAN_MESSAGE', '', 'config_desc_modules.adminBan.DEFAULT_BAN_MESSAGE'),
	'SHOW_BANNED_POST' => boolField('config_label_modules.adminBan.SHOW_BANNED_POST', true, 'config_desc_modules.adminBan.SHOW_BANNED_POST'),
	'ENABLE_APPEALS' => boolField('config_label_modules.adminBan.ENABLE_APPEALS', true, 'config_desc_modules.adminBan.ENABLE_APPEALS'),
	'APPEAL_MAX_LENGTH' => intField('config_label_modules.adminBan.APPEAL_MAX_LENGTH', 1000, 'config_desc_modules.adminBan.APPEAL_MAX_LENGTH', min: 1),
	'APPEAL_COOLDOWN_HOURS' => intField('config_label_modules.adminBan.APPEAL_COOLDOWN_HOURS', 24, 'config_desc_modules.adminBan.APPEAL_COOLDOWN_HOURS'),

	// The front end's own half of ban detection: see static/js/module/akuujin.js.
	'ENABLE_JS_BAN_CHECK' => boolField('config_label_modules.adminBan.ENABLE_JS_BAN_CHECK', true, 'config_desc_modules.adminBan.ENABLE_JS_BAN_CHECK'),
	'BAN_MARKER_COOKIE' => stringField('config_label_modules.adminBan.BAN_MARKER_COOKIE', 'yay', 'config_desc_modules.adminBan.BAN_MARKER_COOKIE'),
	'BAN_MARKER_URL' => stringField('config_label_modules.adminBan.BAN_MARKER_URL', '', 'config_desc_modules.adminBan.BAN_MARKER_URL'),
];
