<?php
// by komeo
class mod_antiflood extends moduleHelper {
	private $RENZOKU3 = 0; // Seconds before a new thread can be made

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->RENZOKU3 = $this->config['ModuleSettings']['RENZOKU3'];
	}

	public function getModuleName() {
		return __CLASS__.' : Anti-flood';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}
	
	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $file, $thread_uid, $imgWH){
		if (!$thread_uid) {
			$threadSingleton = threadSingleton::getInstance();
			$globalHTML = new globalHTML($this->board);

			$tempFileName = $file->getTemporaryFileName();

			$lastThreadTimestamp = $threadSingleton->getLastThreadTimeFromBoard($this->board);
			if(!$lastThreadTimestamp) return;
			$currentTimestamp = new DateTime();
			$timestampFromDatabase = new DateTime($lastThreadTimestamp);

			$currentTimestampUnix      = $currentTimestamp->getTimestamp();
			$timestampFromDatabaseUnix = $timestampFromDatabase->getTimestamp();
			
			$timeDifference = $currentTimestampUnix - $timestampFromDatabaseUnix;
			if ($timeDifference < $this->RENZOKU3) {
				
				if ($tempFileName != NULL) {
					unlink($tempFileName);
				}
				$globalHTML->error('ERROR: Please wait a few seconds before creating a new thread.');
			}
		}
	}
}
?>
