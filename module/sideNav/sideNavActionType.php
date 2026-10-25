<?php

namespace Kokonotsuba\Modules\sideNav;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum sideNavActionType: string implements actionTypeDefinition {
	case SIDE_NAV = 'content.side_nav';

	public function label(): string {
		return match ($this) {
			self::SIDE_NAV => _T('sidenav_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::CONTENT;
	}

	public function isDefault(): bool {
		return true;
	}
}
