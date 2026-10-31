<?php

namespace Kokonotsuba\front_pages;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types for front page pages and the logo. */
enum frontPageActionType: string implements actionTypeDefinition {
	case CREATE = 'page.create';
	case EDIT = 'page.edit';
	case DELETE = 'page.delete';
	case LOGO = 'page.logo';

	public function label(): string {
		return match ($this) {
			self::CREATE => _T('front_page_log_type_create'),
			self::EDIT => _T('front_page_log_type_edit'),
			self::DELETE => _T('front_page_log_type_delete'),
			self::LOGO => _T('front_page_log_type_logo'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::CONTENT;
	}

	public function isDefault(): bool {
		return true;
	}
}
