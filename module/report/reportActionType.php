<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum reportActionType: string implements actionTypeDefinition {
	case REPORTS = 'tool.report';

	public function label(): string {
		return match ($this) {
			self::REPORTS => _T('report_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::TOOL;
	}

	public function isDefault(): bool {
		return true;
	}
}
