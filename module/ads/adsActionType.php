<?php

namespace Kokonotsuba\Modules\ads;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types this module writes. */
enum adsActionType: string implements actionTypeDefinition {
	case ADS = 'content.ad';

	public function label(): string {
		return match ($this) {
			self::ADS => _T('ads_log_type'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::CONTENT;
	}

	public function isDefault(): bool {
		return true;
	}
}
