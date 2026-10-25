<?php

namespace Kokonotsuba\Modules\perceptualBan;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum perceptualBanActionType: string implements actionTypeDefinition {
	case TRIGGER = 'file_ban.trigger';
	case MANAGE = 'tool.file_ban';

	public function label(): string {
		return match ($this) {
			self::TRIGGER => _T('perceptual_log_type_trigger'),
			self::MANAGE => _T('perceptual_log_type_manage'),
		};
	}

	public function group(): actionTypeGroup {
		return match ($this) {
			self::TRIGGER => actionTypeGroup::BAN,
			self::MANAGE => actionTypeGroup::TOOL,
		};
	}

	public function isDefault(): bool {
		return true;
	}
}
