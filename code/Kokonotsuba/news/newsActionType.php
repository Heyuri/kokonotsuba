<?php

namespace Kokonotsuba\news;

use Kokonotsuba\action_log\actionTypeDefinition;
use Kokonotsuba\action_log\actionTypeGroup;

use function Kokonotsuba\libraries\_T;

/** Action log types for front page news. */
enum newsActionType: string implements actionTypeDefinition {
	case CREATE = 'news.create';
	case EDIT = 'news.edit';
	case DELETE = 'news.delete';

	public function label(): string {
		return match ($this) {
			self::CREATE => _T('news_log_type_create'),
			self::EDIT => _T('news_log_type_edit'),
			self::DELETE => _T('news_log_type_delete'),
		};
	}

	public function group(): actionTypeGroup {
		return actionTypeGroup::CONTENT;
	}

	public function isDefault(): bool {
		return true;
	}
}
