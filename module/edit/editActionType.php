<?php

namespace Kokonotsuba\Modules\edit;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum editActionType: string implements actionTypeDefinition {
	case EDIT = 'post.edit';

	public function label(): string {
		return match ($this) {
			self::EDIT => _T('edit_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::POST;
	}

	public function isDefault(): bool {
		return true;
	}
}
