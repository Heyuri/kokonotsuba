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

		$this->moduleContext->moduleEngine->addListener('RegistBeforeCommit', function ($name, &$email, &$emailForInsertion, &$sub, &$com, &$category, &$age, $file, $isReply, &$status, $thread, &$poster_hash) {
			$this->onBeforeCommit($isReply, $com);  // Call the method to modify the form
		});
	}
	
	private function onBeforeCommit(bool $isReply, string $comment): void{
		// flood/spam-prevention logic for posts (replies and threads) as a whole
		$this->preventFloodPost($comment);
		
		// reply-specific logic
		// Commented out for now
		if($isReply) {
			//$this->preventFloodReply();
		}
		// prevent flooding for thread submissions
		else {
			$this->preventFloodThread();
		}
		return;
	}

	private function preventFloodPost(string $comment): void {
		// check if the comment is repeated in the last 50 posts (instance-wide) for if the post is repeated
		// it needs to be instance wide - mainly to prevent stuff like lazy bot spammers
		//$isRepeatedComment = $this->moduleContext->postService->isRepeatedComment($comment, 5);

		// if its a repeated comment and isn't the default comment - reject the post

	}

	private function preventFloodThread(): void {		
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