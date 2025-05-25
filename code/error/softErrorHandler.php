<?php
//Soft error handler koko
class softErrorHandler {
	private globalHTML $globalHTML;

	public function __construct(globalHTML $globalHTML) {
		$this->globalHTML = $globalHTML;
	}

	public function handleAuthError(\Kokonotsuba\Root\Constants\userRole $minimumRole) {
		$staffSession = new staffAccountFromSession;
		$authRoleLevel = $staffSession->getRoleLevel() ?? \Kokonotsuba\Root\Constants\userRole::LEV_NONE;
		
		//handle cases
		if($authRoleLevel === \Kokonotsuba\Root\Constants\userRole::LEV_NONE) {
			$this->globalHTML->error("You aren't logged in!"); //this user isn't logged in!
		}
		
		if(!$authRoleLevel->isAtLeast($minimumRole)) {
			$this->globalHTML->error("You aren't authorized to view this page!"); //forbidden from viewing page
		}
	}

}
