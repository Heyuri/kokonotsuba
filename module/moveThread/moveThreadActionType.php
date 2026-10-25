<?php

namespace Kokonotsuba\Modules\moveThread;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum moveThreadActionType: string implements actionTypeDefinition {
	case MOVE = 'post.move';

	public function label(): string {
		return match ($this) {
			self::MOVE => _T('movethread_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::POST;
	}

	public function isDefault(): bool {
		return true;
	}
}
