<?php
// by komeo

namespace Kokonotsuba\Modules\antiFlood;

use BoardException;
use DateTime;
use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private readonly int $RENZOKU3; // Seconds before a new thread can be made

	public function getName(): string {
		return 'Anti-flood module';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->RENZOKU3 = $this->getConfig('ModuleSettings.RENZOKU3', 0);

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread) {
			$this->onBeforeCommit($isReply);  // Call the method to modify the form
		});
	}
	
	private function onBeforeCommit(bool $isReply): void{
		if($isReply) {
			return;
		}
		
		$lastThreadTimestamp = $this->moduleContext->threadRepository->getLastThreadTimeFromBoard($this->moduleContext->board);
		if(!$lastThreadTimestamp) return;
		$currentTimestamp = new DateTime();
		$timestampFromDatabase = new DateTime($lastThreadTimestamp);

		$currentTimestampUnix      = $currentTimestamp->getTimestamp();
		$timestampFromDatabaseUnix = $timestampFromDatabase->getTimestamp();
			
		$timeDifference = $currentTimestampUnix - $timestampFromDatabaseUnix;
			
		if ($timeDifference < $this->RENZOKU3) {
			throw new BoardException('ERROR: Please wait a few seconds before creating a new thread.');
		}
	}
}
?>
