<?php
/*
Mod_Readonly - Add this to the boards config to make it admin-only
*/
class mod_readonly extends moduleHelper {
	private $ALLOWREPLY, $MINIMUM_ROLE; // Allow replies

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);
		$this->ALLOWREPLY = $this->config['ModuleSettings']['ALLOW_REPLY'];
		$this->MINIMUM_ROLE = $this->config['ModuleSettings']['MINIMUM_ROLE'];
	}

	public function getModuleName(){
		return 'mod_readonly : Read-Only Board';
	}

	public function getModuleVersionInfo(){
		return '7th.Release.dev (v140606)';
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo){
		$staffSession = new staffAccountFromSession;
		
		$roleLevel = $staffSession->getRoleLevel();

		$globalHTML = new globalHTML($this->board);

		$resto = $_POST['resto'] ?? 0;	

		if($this->ALLOWREPLY && $resto) return;
		if($roleLevel->isLessThan(\Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR)){
			$globalHTML->error('New posts cannot be made at this time.');
		}
	}
}
