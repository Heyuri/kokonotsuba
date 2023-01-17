<?php
class mod_csrf_prevent extends ModuleHelper {
	
	public function __construct($PMS) {
		parent::__construct($PMS);
	}

	public function getModuleName(){
		return 'mod_csrf_prevent : 防止偽造跨站請求 (CSRF)';
	}

	public function getModuleVersionInfo(){
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo, $isReply){
		$CSRFdetectd = false;
		/* Check HTTP_REFERER (to prevent cross-site form)
		 *  1. No HTTP_REFERER
		 *  2. HTTP_REFERER is not in this domain
		 */
		if(!strpos($_SERVER['HTTP_REFERER']??'', fullURL()))
			$CSRFdetectd = true;

		if($CSRFdetectd) error('ERROR: CSRF detected!');
	}
}//End-Of-Module

