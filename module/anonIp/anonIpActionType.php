<?php

namespace Kokonotsuba\Modules\anonIp;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum anonIpActionType: string implements actionTypeDefinition {
	case ANON_IP = 'tool.anon_ip';

	public function label(): string {
		return match ($this) {
			self::ANON_IP => _T('anonip_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::TOOL;
	}

	public function isDefault(): bool {
		return true;
	}
}
