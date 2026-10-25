<?php

namespace Kokonotsuba\Modules\tripcode;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum capcodeActionType: string implements actionTypeDefinition {
	case CAPCODES = 'tool.capcode';

	public function label(): string {
		return match ($this) {
			self::CAPCODES => _T('capcode_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::TOOL;
	}

	public function isDefault(): bool {
		return true;
	}
}
