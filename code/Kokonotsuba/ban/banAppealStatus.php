<?php

namespace Kokonotsuba\ban;

use function Kokonotsuba\libraries\_T;

/**
 * Lifecycle of an appeal against a ban.
 *
 * Approving revokes the ban (or shortens it); denying leaves the ban alone and lets the user try
 * again once the cooldown has passed. Both are terminal for the appeal itself.
 */
enum banAppealStatus: int {
	case PENDING = 0;
	case APPROVED = 1;
	case DENIED = 2;

	public static function fromValue(mixed $value): self {
		return self::tryFrom((int) $value) ?? self::PENDING;
	}

	public function isPending(): bool {
		return $this === self::PENDING;
	}

	public function label(): string {
		return match ($this) {
			self::PENDING => _T('ban_appeal_status_pending'),
			self::APPROVED => _T('ban_appeal_status_approved'),
			self::DENIED => _T('ban_appeal_status_denied'),
		};
	}

	public function rowCssClass(): string {
		return match ($this) {
			self::PENDING => 'banAppealPending',
			self::APPROVED => 'banAppealApproved',
			self::DENIED => 'banAppealDenied',
		};
	}
}
