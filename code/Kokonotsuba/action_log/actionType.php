<?php

namespace Kokonotsuba\action_log;

/**
 * The kind of thing a log entry records.
 *
 * Stored in actionlog.action_type so the log can be filtered on the event itself rather than on
 * substrings of its prose. Modules add their own through actionLoggerService::registerType(),
 * which is what puts an extra checkbox on the action log filter form.
 */
enum actionType: string {
	case POST_REGISTER = 'post.register';
	case POST_DELETE = 'post.delete';
	case POST_FILE_DELETE = 'post.file_delete';
	case POST_RESTORE = 'post.restore';
	case POST_PURGE = 'post.purge';
	case POST_EDIT = 'post.edit';
	case POST_MOVE = 'post.move';
	case POST_FLAG = 'post.flag';

	case BAN_ISSUE = 'ban.issue';
	case BAN_EDIT = 'ban.edit';
	case BAN_REVOKE = 'ban.revoke';
	case BAN_APPEAL = 'ban.appeal';
	case BAN_TRIGGER = 'ban.trigger';

	case ACCOUNT_LOGIN = 'account.login';
	case ACCOUNT_LOGIN_FAILED = 'account.login_failed';
	case ACCOUNT_CREATE = 'account.create';
	case ACCOUNT_PASSWORD = 'account.password';

	case BOARD_REBUILD = 'board.rebuild';
	case BOARD_CONFIG = 'board.config';

	case CONTENT_BANNER = 'content.banner';
	case CONTENT_BLOTTER = 'content.blotter';
	case CONTENT_AD = 'content.ad';

	case TOOL_CAPCODE = 'tool.capcode';
	case TOOL_REPORT = 'tool.report';
	case TOOL_FILE_BAN = 'tool.file_ban';
	case TOOL_PM = 'tool.pm';
	case TOOL_ANON_IP = 'tool.anon_ip';

	case OTHER = 'other';

	/** Label shown next to the checkbox on the filter form and in the log table. */
	public function label(): string {
		return match ($this) {
			self::POST_REGISTER => 'Post made',
			self::POST_DELETE => 'Post deleted',
			self::POST_FILE_DELETE => 'File deleted',
			self::POST_RESTORE => 'Post restored',
			self::POST_PURGE => 'Post purged',
			self::POST_EDIT => 'Post edited',
			self::POST_MOVE => 'Thread moved',
			self::POST_FLAG => 'Post flagged',
			self::BAN_ISSUE => 'Ban issued',
			self::BAN_EDIT => 'Ban edited',
			self::BAN_REVOKE => 'Ban revoked',
			self::BAN_APPEAL => 'Ban appeal',
			self::BAN_TRIGGER => 'Ban enforced',
			self::ACCOUNT_LOGIN => 'Logged in',
			self::ACCOUNT_LOGIN_FAILED => 'Failed log-in',
			self::ACCOUNT_CREATE => 'Account created',
			self::ACCOUNT_PASSWORD => 'Password reset',
			self::BOARD_REBUILD => 'Rebuild',
			self::BOARD_CONFIG => 'Config change',
			self::CONTENT_BANNER => 'Banners',
			self::CONTENT_BLOTTER => 'Blotter',
			self::CONTENT_AD => 'Ads',
			self::TOOL_CAPCODE => 'Capcodes',
			self::TOOL_REPORT => 'Reports',
			self::TOOL_FILE_BAN => 'File bans',
			self::TOOL_PM => 'Private messages',
			self::TOOL_ANON_IP => 'IP anonymization',
			self::OTHER => 'Uncategorised',
		};
	}

	/** Section this type sits under on the filter form. */
	public function group(): actionTypeGroup {
		return match ($this) {
			self::POST_REGISTER, self::POST_DELETE, self::POST_FILE_DELETE, self::POST_RESTORE,
			self::POST_PURGE, self::POST_EDIT, self::POST_MOVE, self::POST_FLAG => actionTypeGroup::POST,

			self::BAN_ISSUE, self::BAN_EDIT, self::BAN_REVOKE, self::BAN_APPEAL,
			self::BAN_TRIGGER => actionTypeGroup::BAN,

			self::ACCOUNT_LOGIN, self::ACCOUNT_LOGIN_FAILED, self::ACCOUNT_CREATE,
			self::ACCOUNT_PASSWORD => actionTypeGroup::ACCOUNT,

			self::BOARD_REBUILD, self::BOARD_CONFIG => actionTypeGroup::BOARD,

			self::CONTENT_BANNER, self::CONTENT_BLOTTER, self::CONTENT_AD => actionTypeGroup::CONTENT,

			self::TOOL_CAPCODE, self::TOOL_REPORT, self::TOOL_FILE_BAN, self::TOOL_PM,
			self::TOOL_ANON_IP, self::OTHER => actionTypeGroup::TOOL,
		};
	}

	/** Whether the filter form ticks this by default. */
	public function isDefault(): bool {
		return $this !== self::POST_REGISTER;
	}
}
