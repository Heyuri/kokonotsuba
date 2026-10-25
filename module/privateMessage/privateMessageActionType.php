<?php

namespace Kokonotsuba\Modules\privateMessage;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum privateMessageActionType: string implements actionTypeDefinition {
	case MESSAGES = 'tool.pm';

	public function label(): string {
		return match ($this) {
			self::MESSAGES => _T('pm_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::TOOL;
	}

	public function isDefault(): bool {
		return true;
	}
}
