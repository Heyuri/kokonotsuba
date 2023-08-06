<?php
// by komeo
class mod_antiflood extends ModuleHelper {
	private $mypage;
	private $CONNECTION_STRING = CONNECTION_STRING;
	private $RENZOKU3 = 10; // Seconds before a new thread can be made

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Anti-flood';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}
	
	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo, $isReply){
		if ($isReply == 0) {
			$PIO = PMCLibrary::getPIOInstance();
			$last = $PIO->getLastThreadTime();
			$now = $_SERVER["REQUEST_TIME"];
			if ($now - $last < $this->RENZOKU3) error('ERROR: Please wait a few seconds before creating a new thread.');
		}
	}
}
?>