<?php

namespace Kokonotsuba\ban;

use function Kokonotsuba\libraries\_T;

/**
 * A thing a ban can stop someone doing.
 *
 * These are the checkpoints the engine ships with. Modules can add their own through
 * banService::registerCheckpoint(), which is what puts an extra checkbox on the ban form.
 */
enum banCheckpoint: string {
	case POST = 'post';
	case REPORT = 'report';
	case BANNER = 'banner';
	case SOUDANE = 'soudane';
	case PM = 'pm';

	/** Label shown next to the checkbox on the ban form and in the ban table. */
	public function label(): string {
		return match ($this) {
			self::POST => _T('ban_checkpoint_post'),
			self::REPORT => _T('ban_checkpoint_report'),
			self::BANNER => _T('ban_checkpoint_banner'),
			self::SOUDANE => _T('ban_checkpoint_soudane'),
			self::PM => _T('ban_checkpoint_pm'),
		};
	}

	/** Message shown to someone a ban stopped here. */
	public function blockedMessage(): string {
		return match ($this) {
			self::POST => _T('ban_blocked_post'),
			self::REPORT => _T('ban_blocked_report'),
			self::BANNER => _T('ban_blocked_banner'),
			self::SOUDANE => _T('ban_blocked_soudane'),
			self::PM => _T('ban_blocked_pm'),
		};
	}

	/** Whether the ban form ticks this by default. */
	public function isDefault(): bool {
		return match ($this) {
			self::POST, self::REPORT, self::BANNER => true,
			self::SOUDANE, self::PM => false,
		};
	}
}
