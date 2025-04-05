<?php
/*
Mod_Readonly - Add this to the boards config to make it admin-only
*/
class mod_readonly extends moduleHelper {
	private $ALLOWREPLY, $MINIMUM_ROLE = false; // Allow replies

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);

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
		$globalHTML = new globalHTML($this->board);

		$resto = $_POST['resto'] ?? 0;

		if($this->ALLOWREPLY && $resto) return;
		if($staffSession->getRoleLevel() < $this->MINIMUM_ROLE){ $globalHTML->error('New posts cannot be made at this time.'); }
	}
}
