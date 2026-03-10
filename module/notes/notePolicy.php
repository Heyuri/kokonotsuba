<?php

namespace Kokonotsuba\Modules\notes;

use Kokonotsuba\policy\policyBase;
use Kokonotsuba\userRole;

class notePolicy extends policyBase {
	private noteService $noteService;

	public function setNoteService(noteService $noteService): void {
		$this->noteService = $noteService;
	}

	public function canLeaveNote(): bool {
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_LEAVE_NOTE']);
	}
	
	public function canModifyNote(int $noteId): bool {
		// if the user is the owner of the note, or is an admin, they can delete the note
		if(
			$this->noteService->noteOwnedByAccount($noteId, $this->accountId)
			|| $this->roleLevel->isAtLeast(userRole::LEV_ADMIN)
		) {
			return true;
		}
		
		// otherwise, check if the user has the required role to delete notes
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_DELETE_NOTE']);
	}
}