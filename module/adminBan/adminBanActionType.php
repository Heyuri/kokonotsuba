<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum adminBanActionType: string implements actionTypeDefinition {
	case EDIT = 'ban.edit';
	case REVOKE = 'ban.revoke';
	case APPEAL = 'ban.appeal';

	public function label(): string {
		return match ($this) {
			self::EDIT => _T('ban_log_type_edit'),
			self::REVOKE => _T('ban_log_type_revoke'),
			self::APPEAL => _T('ban_log_type_appeal'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::BAN;
	}

	public function isDefault(): bool {
		return true;
	}
}
