<?php
// by komeo
class mod_antiflood extends ModuleHelper {
	private $mypage;
	private $RENZOKU3 = 0; // Seconds before a new thread can be made

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->RENZOKU3 = $this->config['ModuleSettings']['RENZOKU3'];
		
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Anti-flood';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}
	
	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $resto, $imgWH){
		if (!$resto) {
			$PIO = PMCLibrary::getPIOInstance();
			$last = $PIO->getLastThreadTime();
			$now = $_SERVER["REQUEST_TIME"];
			if ($now - $last < $this->RENZOKU3) {
				if ($dest != NULL) unlink($dest); // Delete image
				error('ERROR: Please wait a few seconds before creating a new thread.');
			} 
		}
	}
}
?>
