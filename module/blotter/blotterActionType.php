<?php

namespace Kokonotsuba\Modules\blotter;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum blotterActionType: string implements actionTypeDefinition {
	case BLOTTER = 'content.blotter';

	public function label(): string {
		return match ($this) {
			self::BLOTTER => _T('blotter_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::CONTENT;
	}

	public function isDefault(): bool {
		return true;
	}
}
