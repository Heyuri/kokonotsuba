<?php

namespace Kokonotsuba\action_log;

/**
 * The kind of thing a log entry records: the types core code writes.
 *
 * Stored in actionlog.action_type so the log can be filtered on the event itself rather than on
 * substrings of its prose. A module keeps its own types in an actionTypeDefinition enum beside its
 * code and registers it with actionLoggerService::registerTypes(), which is what puts its
 * checkboxes on the action log filter form.
 */
enum actionType: string implements actionTypeDefinition {
	case POST_REGISTER = 'post.register';
	case POST_DELETE = 'post.delete';
	case POST_FILE_DELETE = 'post.file_delete';
	case POST_RESTORE = 'post.restore';
	case POST_PURGE = 'post.purge';
	case POST_FLAG = 'post.flag';

	case BAN_ISSUE = 'ban.issue';
	case BAN_TRIGGER = 'ban.trigger';

	case ACCOUNT_LOGIN = 'account.login';
	case ACCOUNT_LOGIN_FAILED = 'account.login_failed';
	case ACCOUNT_CREATE = 'account.create';
	case ACCOUNT_PASSWORD = 'account.password';

	case BOARD_REBUILD = 'board.rebuild';
	case BOARD_CONFIG = 'board.config';

	case OTHER = 'other';

	public function label(): string {
		return match ($this) {
			self::POST_REGISTER => 'Post made',
			self::POST_DELETE => 'Post deleted',
			self::POST_FILE_DELETE => 'File deleted',
			self::POST_RESTORE => 'Post restored',
			self::POST_PURGE => 'Post purged',
			self::POST_FLAG => 'Post flagged',
			self::BAN_ISSUE => 'Ban issued',
			self::BAN_TRIGGER => 'Ban enforced',
			self::ACCOUNT_LOGIN => 'Logged in',
			self::ACCOUNT_LOGIN_FAILED => 'Failed log-in',
			self::ACCOUNT_CREATE => 'Account created',
			self::ACCOUNT_PASSWORD => 'Password reset',
			self::BOARD_REBUILD => 'Rebuild',
			self::BOARD_CONFIG => 'Config change',
			self::OTHER => 'Uncategorised',
		};
	}

	public function group(): actionTypeGroup {
		return match ($this) {
			self::POST_REGISTER, self::POST_DELETE, self::POST_FILE_DELETE, self::POST_RESTORE,
			self::POST_PURGE, self::POST_FLAG => actionTypeGroup::POST,

			self::BAN_ISSUE, self::BAN_TRIGGER => actionTypeGroup::BAN,

			self::ACCOUNT_LOGIN, self::ACCOUNT_LOGIN_FAILED, self::ACCOUNT_CREATE,
			self::ACCOUNT_PASSWORD => actionTypeGroup::ACCOUNT,

			self::BOARD_REBUILD, self::BOARD_CONFIG => actionTypeGroup::BOARD,

			self::OTHER => actionTypeGroup::TOOL,
		};
	}

	public function isDefault(): bool {
		return $this !== self::POST_REGISTER;
	}
}
