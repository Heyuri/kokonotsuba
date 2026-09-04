<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\userRole;

/**
 * Role gate for the ban tools.
 *
 * Filing and lifting bans share CAN_BAN, which is what the ban button has always used. Appeals
 * get their own two capabilities so an instance can let janitors read the queue without letting
 * them overturn a moderator's ban.
 */
class banPolicy {
	public function __construct(
		private readonly array $authLevels,
		private readonly userRole $roleLevel,
	) {}

	public function getBanRole(): userRole {
		return $this->authLevels['CAN_BAN'] ?? userRole::LEV_MODERATOR;
	}

	public function getViewAppealsRole(): userRole {
		return $this->authLevels['CAN_VIEW_BAN_APPEALS'] ?? userRole::LEV_MODERATOR;
	}

	public function getActionAppealRole(): userRole {
		return $this->authLevels['CAN_ACTION_BAN_APPEAL'] ?? userRole::LEV_MODERATOR;
	}

	public function canBan(): bool {
		return $this->roleLevel->isAtLeast($this->getBanRole());
	}

	/** Lifting a ban is the same capability as filing one. */
	public function canRevoke(): bool {
		return $this->canBan();
	}

	public function canViewAppeals(): bool {
		return $this->roleLevel->isAtLeast($this->getViewAppealsRole());
	}

	public function canActionAppeals(): bool {
		return $this->roleLevel->isAtLeast($this->getActionAppealRole());
	}

	public function canViewIpAddresses(): bool {
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_VIEW_IP_ADDRESSES'] ?? userRole::LEV_MODERATOR);
	}
}
