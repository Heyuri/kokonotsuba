<?php
//Soft error handler koko
class softErrorHandler {
	private $config, $board;

	public function __construct($board) {
		$config = $board->loadBoardConfig();
		$this->board = $board;
		$this->config = $config;
	}

	public function handleAuthError($minimumRole) {
		$globalHTML = new globalHTML($this->board);
		$staffSession = new staffAccountFromSession;
		$authRoleLevel = $staffSession->getRoleLevel() ?? $this->config['roles']['LEV_NONE'];
		
		//handle cases
		if(!$authRoleLevel) $globalHTML->error("You aren't logged in!"); //this user isn't logged in!
		if($authRoleLevel < $minimumRole) $globalHTML->error("You aren't authorized to view this page!"); //forbidden from viewing page
	}

}
