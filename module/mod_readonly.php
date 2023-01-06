<?php
/*
Mod_Readonly - Add this to the boards config to make it admin-only
*/
class mod_readonly extends ModuleHelper {
	private $READONLY  = true; // Set read-only
	private $ALLOWREPLY = true; // Allow replies

	public function __construct($PMS) {
		parent::__construct($PMS);
	}

	public function getModuleName(){
		return 'mod_readonly : Read-Only Board';
	}

	public function getModuleVersionInfo(){
		return '7th.Release.dev (v140606)';
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo){
		$pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';
		$resto = isset($_POST['resto']) ? $_POST['resto'] : 0;

		if($this->ALLOWREPLY && $resto) return;
		if($this->READONLY && valid() < LEV_MODERATOR && ($name != CAP_NAME && $pwd != CAP_PASS)){ error('New threads cannot be made at this time.'); }
	}
}
