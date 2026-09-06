<?php

namespace Kokonotsuba\Modules\hostNotes;

use Kokonotsuba\policy\policyBase;
use Kokonotsuba\userRole;

class hostNotePolicy extends policyBase {
	private hostNoteService $hostNoteService;

	public function setHostNoteService(hostNoteService $hostNoteService): void {
		$this->hostNoteService = $hostNoteService;
	}

	/** Reading notes under a post shows no address, so it sits below filing one. */
	public function canViewHostNote(): bool {
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_VIEW_HOST_NOTE'] ?? userRole::LEV_JANITOR);
	}

	public function canLeaveHostNote(): bool {
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_LEAVE_HOST_NOTE'] ?? userRole::LEV_MODERATOR);
	}

	public function canModifyHostNote(int $noteId): bool {
		// the author of a note can always edit or delete it
		if ($this->hostNoteService->noteOwnedByAccount($this->accountId, $noteId)) {
			return true;
		}

		return $this->roleLevel->isAtLeast($this->authLevels['CAN_DELETE_HOST_NOTE'] ?? userRole::LEV_ADMIN);
	}
}
