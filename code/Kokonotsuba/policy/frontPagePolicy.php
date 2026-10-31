<?php

namespace Kokonotsuba\policy;

use Kokonotsuba\userRole;

/** What the viewer may do with front page news and pages. Editors also see drafts. */
class frontPagePolicy extends policyBase {
	public function canEditNews(): bool {
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_EDIT_NEWS'] ?? userRole::LEV_ADMIN);
	}

	public function canEditPages(): bool {
		return $this->roleLevel->isAtLeast($this->authLevels['CAN_EDIT_PAGES'] ?? userRole::LEV_ADMIN);
	}

	/** The logo and the rest of the front page's own settings. */
	public function canEditSite(): bool {
		return $this->canEditNews() && $this->canEditPages();
	}
}
