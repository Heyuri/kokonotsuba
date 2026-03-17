<?php
// by komeo

namespace Kokonotsuba\Modules\antiFlood;

require_once __DIR__ . '/submissionRepository.php';
require_once __DIR__ . '/submissionService.php';
require_once __DIR__ . '/submissionLib.php';

use Kokonotsuba\error\BoardException;
use DateTime;
use DateTimeZone;
use Kokonotsuba\module_classes\abstractModuleMain;
use Kokonotsuba\Modules\antiFlood\submissionService;

use function Kokonotsuba\libraries\getBoardsByUIDs;
use function Kokonotsuba\libraries\rebuildBoardsByArray;
use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\sendAjaxAndDetach;
use function Puchiko\request\redirect;
use function Kokonotsuba\Modules\antiFlood\getSubmissionService;

class moduleMain extends abstractModuleMain {
	private readonly int $RENZOKU3; // Seconds before a new thread can be made
	private submissionService $submissionService;

	public function getName(): string {
		return 'Anti-flood module';
	}

	public function getVersion(): string {
		return 'Koko BBS Release 1';
	}

	public function initialize(): void {
		$this->RENZOKU3 = $this->getConfig('ModuleSettings.RENZOKU3', 0);
		
		// Initialize submission service for thread flood tracking
		$this->submissionService = getSubmissionService();

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
		if(is_countable($repeatedPosts) && sizeof($repeatedPosts) > $this->getConfig('ModuleSettings.ALLOWED_COMMENT_REPETITIONS', 5)) {
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

		// if the time difference is a valid number and the difference is below the minimum time window then prevent the thread creation 
		if (is_numeric($timeDifference) &&  $this->RENZOKU3 && $timeDifference < $this->RENZOKU3) {
			throw new BoardException('ERROR: Please wait a few seconds before creating a new thread.');
		}
		// otherwise, record the submission so the next thread can check against it
		 else {
			$this->submissionService->recordSubmission($this->moduleContext->board->getBoardUID());
		}
	}

	private function calculateTimeDifference(): ?float {
		// Fetch the last submission timestamp for this board from the submission table.
		// Database stores millisecond precision (TIMESTAMP(3)), so we calculate
		// the difference accounting for microseconds to get accurate millisecond-level granularity.
		$lastSubmissionTimestamp = $this->submissionService->getLastSubmissionTimeForBoard($this->moduleContext->board->getBoardUID());

		// Don't bother if there's no last submission - there's no anti-flooding to calculate
		if(!$lastSubmissionTimestamp) return null;
		
		// Initialize UTC timezone
		$utcTimezone = new DateTimeZone('UTC');

		$currentTimestamp = new DateTime('now', $utcTimezone);
		$timestampFromDatabase = new DateTime($lastSubmissionTimestamp, $utcTimezone);

		// Calculate difference including microsecond precision
		// This gives accurate millisecond-level granularity for flood prevention
		$currentSeconds = $currentTimestamp->getTimestamp();
		$currentMicros = (int)$currentTimestamp->format('u');
		
		$databaseSeconds = $timestampFromDatabase->getTimestamp();
		$databaseMicros = (int)$timestampFromDatabase->format('u');

		// Difference in seconds with microsecond precision (fractional seconds)
		$differenceInSeconds = ($currentSeconds - $databaseSeconds) + 
							   (($currentMicros - $databaseMicros) / 1000000.0);

		return (float)$differenceInSeconds;
	}
}