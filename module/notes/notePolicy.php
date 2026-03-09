<?php

namespace Kokonotsuba\module\notes;

use Kokonotsuba\userRole;

class notePolicy extends{
	public function canLeaveNote(): bool {
		return $this->currentUserRole >= userRole::LEV_JANITOR;
	}
}