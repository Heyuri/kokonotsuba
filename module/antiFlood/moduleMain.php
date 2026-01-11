<?php
// by komeo

namespace Kokonotsuba\Modules\antiFlood;

use BoardException;
use DateTime;
use DateTimeZone;
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
			$this->onBeforeCommit($isReply, $com);
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
		// get the time window for comments it'll grab.
		// measured in seconds
		$timeWindow = $this->getConfig('ModuleSettings.SAME_COMMENT_TIME_WINDOW', 10);

		// return early if its 0 or negative
		if(!$timeWindow || $timeWindow <= 0) {
			return;
		}

		// get the default comment
		$defaultComment = $this->getConfig('DEFAULT_NOCOMMENT');

		// check if the comment is repeated in a certain time period
		// it needs to be instance wide - mainly to prevent stuff like lazy bot spammers
		$repeatedPosts = $this->moduleContext->postService->getRepeatedPosts($comment, $defaultComment, $timeWindow);

		// if its a repeated comment and isn't the default comment - silently delete the previous threads and redirect
		// Deleted posts are still visible to moderators so false-positives can be restored
		// It doesn't serve an error because the bot or bot operator may have an automated way of detecting server errors and adjusting output
		if($repeatedPosts) {
			// now delete the posts themselves
			$this->moduleContext->postService->removePosts($repeatedPosts);
			
			// get the index file name (default to returning them to the last page if not)
			$index = $this->getConfig('LIVE_INDEX_FILE', 'back');
			
			// send dummy json output for ajax users
			if(isJavascriptRequest()) {
				// send ajax
				sendAjaxAndDetach(['redirectUrl' => $index]);

				$this->handlePageRebuilding($repeatedPosts);

				exit;
			} else {
				$this->handlePageRebuilding($repeatedPosts);
				
				// redirect to index
				redirect($index);
			}
		}
	}

	private function handlePageRebuilding(array $postUids): void {
		// get the board uids of posts
		$boardUids = $this->moduleContext->postRepository->getBoardUidsFromPostUids($postUids);

		// dont bother if they dont exist
		if(!$boardUids) {
			return;
		}

		// now get the boards
		$boardsToRebuild = getBoardsByUIDs($boardUids);

		// then rebuild
		rebuildBoardsByArray($boardsToRebuild);
	}

	private function preventFloodThread(): void {	
		// get time difference from latest thread from board				
		$timeDifference = $this->calculateTimeDifference();

		// if the time diference is a valid integer and the difference is below the minimum time window then prevent the thread creation 
		if (is_int($timeDifference) &&  $this->RENZOKU3 && $timeDifference < $this->RENZOKU3) {
			throw new BoardException('ERROR: Please wait a few seconds before creating a new thread.');
		}
	}

	private function calculateTimeDifference(): ?int {
		// fetch the last timezone (local DB/server timezone)
		$lastThreadTimestamp = $this->moduleContext->threadRepository->getLastThreadTimeFromBoard($this->moduleContext->board);

		// don't bother if theres no last timezone - theres no anti-flooding to calcuate
		if(!$lastThreadTimestamp) return null;
		
		// init UTC timezone
		$utcTimezone = new DateTimeZone('UTC');

		$currentTimestamp = new DateTime('now', $utcTimezone);
		$timestampFromDatabase = new DateTime($lastThreadTimestamp, $utcTimezone);


		$currentTimestampUnix      = $currentTimestamp->getTimestamp();
		$timestampFromDatabaseUnix = $timestampFromDatabase->getTimestamp();

		return $currentTimestampUnix - $timestampFromDatabaseUnix;
	}
}