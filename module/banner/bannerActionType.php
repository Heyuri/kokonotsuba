<?php

namespace Kokonotsuba\Modules\banner;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum bannerActionType: string implements actionTypeDefinition {
	case BANNERS = 'content.banner';

	public function label(): string {
		return match ($this) {
			self::BANNERS => _T('banner_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::CONTENT;
	}

	public function isDefault(): bool {
		return true;
	}
}
