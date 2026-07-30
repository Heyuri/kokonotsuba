<?php

namespace Kokonotsuba\Modules\report;

use Kokonotsuba\userRole;

/**
 * Role gate for the report queue.
 *
 * Every capability maps to an entry in $config['AuthLevels'] so instances can move the line
 * themselves. The shipped defaults let janitors see and approve reports but not dismiss or
 * clear them, which is why viewing and approving are separate capabilities.
 */
class reportPolicy {
	public function __construct(
		private readonly array $authLevels,
		private readonly userRole $roleLevel,
	) {}

	/** Minimum role needed to reach any part of the report queue. */
	public function getViewRole(): userRole {
		return $this->authLevels['CAN_VIEW_REPORTS'] ?? userRole::LEV_JANITOR;
	}

	public function getApproveRole(): userRole {
		return $this->authLevels['CAN_APPROVE_REPORT'] ?? userRole::LEV_JANITOR;
	}

	public function getDismissRole(): userRole {
		return $this->authLevels['CAN_DISMISS_REPORT'] ?? userRole::LEV_MODERATOR;
	}

	public function getClearRole(): userRole {
		return $this->authLevels['CAN_CLEAR_POST_REPORTS'] ?? userRole::LEV_MODERATOR;
	}

	public function canView(): bool {
		return $this->roleLevel->isAtLeast($this->getViewRole());
	}

	/** Approving a report deletes the reported post, so this also requires deletion rights. */
	public function canApprove(): bool {
		$deleteRole = $this->authLevels['CAN_DELETE_POST'] ?? userRole::LEV_JANITOR;

		return $this->roleLevel->isAtLeast($this->getApproveRole())
			&& $this->roleLevel->isAtLeast($deleteRole);
	}

	public function canDismiss(): bool {
		return $this->roleLevel->isAtLeast($this->getDismissRole());
	}

	public function canClearPostReports(): bool {
		return $this->roleLevel->isAtLeast($this->getClearRole());
	}

	/** Whether this viewer may see the IP addresses attached to reports. */
	public function canViewIpAddresses(): bool {
		$ipRole = $this->authLevels['CAN_VIEW_IP_ADDRESSES'] ?? userRole::LEV_MODERATOR;

		return $this->roleLevel->isAtLeast($ipRole);
	}
}
