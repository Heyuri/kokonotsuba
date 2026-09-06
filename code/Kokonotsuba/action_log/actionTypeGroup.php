<?php

namespace Kokonotsuba\action_log;

/** Section headings the action type checkboxes are laid out under. */
enum actionTypeGroup: string {
	case POST = 'post';
	case BAN = 'ban';
	case ACCOUNT = 'account';
	case BOARD = 'board';
	case CONTENT = 'content';
	case TOOL = 'tool';

	public function label(): string {
		return match ($this) {
			self::POST => 'Posts',
			self::BAN => 'Bans',
			self::ACCOUNT => 'Accounts',
			self::BOARD => 'Boards',
			self::CONTENT => 'Content',
			self::TOOL => 'Tools',
		};
	}
}
