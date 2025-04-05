<?php
// by komeo
class mod_antiflood extends moduleHelper {
	private $mypage;
	private $RENZOKU3 = 0; // Seconds before a new thread can be made

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);
		
		$this->RENZOKU3 = $this->config['ModuleSettings']['RENZOKU3'];
		
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : Anti-flood';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}
	
	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $thread_uid, $imgWH){
		if (!$thread_uid) {
			$PIO = PIOPDO::getInstance();
			$globalHTML = new globalHTML($this->board);

			$lastThreadTimestamp = $PIO->getLastThreadTimeFromBoard($this->board);
			if(!$lastThreadTimestamp) return;
			$currentTimestamp = new DateTime();
			$timestampFromDatabase = new DateTime($lastThreadTimestamp);

			$currentTimestampUnix      = $currentTimestamp->getTimestamp();
			$timestampFromDatabaseUnix = $timestampFromDatabase->getTimestamp();
			
			$timeDifference = $currentTimestampUnix - $timestampFromDatabaseUnix;
			if ($timeDifference < $this->RENZOKU3) {
				if ($dest != NULL) {
					unlink($dest);
				}
				$globalHTML->error('ERROR: Please wait a few seconds before creating a new thread.');
			}
		}
	}
}
?>
