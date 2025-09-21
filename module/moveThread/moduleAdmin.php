<?php

namespace Kokonotsuba\Modules\moveThread;

use BoardException;
use Exception;
use FlagHelper;
use IBoard;
use InvalidArgumentException;
use IPAddress;
use Kokonotsuba\ModuleClasses\abstractModuleAdmin;
use Kokonotsuba\Root\Constants\userRole;
use postDateFormatter;
use postRegistData;
use tripcodeProcessor;

//move thread module
class moduleAdmin extends abstractModuleAdmin {
	private readonly string $myPage;

    public function getRequiredRole(): userRole {
        return $this->getConfig('AuthLevels.CAN_MANAGE_REBUILD');
    }

	public function getName(): string {
		return 'Rebuild tools';
	}

	public function getVersion(): string {
		return 'Koko 2025';
	}

	public function initialize(): void {
		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addRoleProtectedListener(
			$this->getRequiredRole(),
			'ThreadAdminControls',
			function(string &$modControlSection, array &$post) {
				$this->renderMoveThreadButton($modControlSection, $post);
			}
		);
	}

	public function renderMoveThreadButton(string &$modfunc, array $post): void {
		// if it's a thread, apply admin hook list html
		$threadStatus = new FlagHelper($post['status']);

		if($threadStatus->value('ghost')) {
			$modfunc .= '<span class="adminFunctions adminMoveThreadFunction" title="Ghost threads cannot be moved.">[mt]</span>';
		} else {
			$moveThreadButtonUrl = $this->getModulePageURL(
				[
					'thread_uid' => $post['thread_uid']
				], 
				false, 
				true);

			$modfunc .= '<span class="adminFunctions adminMoveThreadFunction">[<a href="' . $moveThreadButtonUrl . '" title="Move thread">MT</a>]</span>';
		}
	}

	private function leavePostInShadowThread(array $originalThread, IBoard $originalBoard, array $newThread, IBoard $destinationBoard) {
		$originalBoardConfig = $originalBoard->loadBoardConfig();

		$postDateFormatter = new postDateFormatter($originalBoardConfig);
		$tripcodeProcessor = new tripcodeProcessor($originalBoardConfig);
		
		$time = $_SERVER['REQUEST_TIME'];
		$now = $postDateFormatter->formatFromTimestamp($time);

		// Generate new post number
		$no = $originalBoard->getLastPostNoFromBoard() + 1;

		// Determine name and capcode
		$capcode = "";
		$name = $originalBoardConfig['SYSTEMCHAN_NAME'];

		$tripcode = '';
		$secure_tripcode = 'System';

		// Generate link to the new thread
		$newThreadUrl = $destinationBoard->getBoardThreadURL($newThread['post_op_number']);
		$moveComment = 'Thread moved to <a href="' . $newThreadUrl . '">'.$destinationBoard->getBoardTitle().'</a>';

		// Prepare post metadata
		$ip = new IPAddress('127.0.0.1');

		$tripcodeProcessor->apply($name, $tripcode, $secure_tripcode, $capcode, \Kokonotsuba\Root\Constants\userRole::LEV_SYSTEM);

		// Get original thread UID
		$originalThreadUid = $originalThread['thread_uid'];


		$postRegistData = new postRegistData(
				$no,
				'SYSTEM',
				$originalThreadUid,
				false,
				'',
				'',
				0,
				'',
				'',
				0,
				0,
				'',
				0,
				0,
				'',
				$now,
				$name,
				$tripcode,
				$secure_tripcode,
				$capcode,
				'',
				'',
				$moveComment,
				$ip,
				false,
				''
			);

		// Add shadow post
		$this->moduleContext->postService->addPostToThread(
			$originalBoard, $postRegistData);
	}


	private function copyThreadToBoard(string $originalThreadUid, IBoard $destinationBoard): string {
		// Step 1: Gather attachments from the original thread
		$filesToCopy = $this->moduleContext->threadRepository->getAllAttachmentsFromThread($originalThreadUid);
	
		// Step 2: Copy the thread and posts, receiving new thread UID and post UID mapping
		$copyResult = $this->moduleContext->threadService->copyThreadAndPosts($originalThreadUid, $destinationBoard);

		if (!is_array($copyResult)) {
			throw new Exception("copyThreadAndPosts() returned a non-array value.");
		}

		if (!isset($copyResult['threadUid'])) {
			throw new Exception("copyThreadAndPosts() result is missing 'threadUid'.");
		}

		if (!is_string($copyResult['threadUid'])) {
			throw new Exception("'threadUid' in copyThreadAndPosts() result is not a string.");
		}

		if (!isset($copyResult['postUidMap'])) {
			throw new Exception("copyThreadAndPosts() result is missing 'postUidMap'.");
		}

		if (!is_array($copyResult['postUidMap'])) {
			throw new Exception("'postUidMap' in copyThreadAndPosts() result is not an array.");
		}

		$newThreadUid   = $copyResult['threadUid'];
		$postUidMapping = $copyResult['postUidMap'];
	
		// Step 3: Copy quote links using the post UID mapping
		$this->moduleContext->quoteLinkService->copyQuoteLinksFromThread($originalThreadUid, $destinationBoard->getBoardUID(), $postUidMapping);
	
		// Step 4: Copy attachments
		$this->copyThreadAttachmentsToBoard($filesToCopy, $destinationBoard, true);
	
		// Step 5: Return the UID of the newly created thread
		return $newThreadUid;
	}	
	
	private function copyThreadAttachmentsToBoard(array $attachments, IBoard $destinationBoard, bool $isCopy = false): void {
		if (empty($attachments)) return;
	
		// Destination board paths and config
		$destBasePath = $destinationBoard->getBoardUploadedFilesDirectory();
		$destConfig = $destinationBoard->loadBoardConfig();
		$destImgPath = $destBasePath . $destConfig['IMG_DIR'];
		$destThumbPath = $destBasePath . $destConfig['THUMB_DIR'];
	
		// Source board paths and config
		$srcBoardUID = $attachments[0]['boardUID'];
		$srcBoard = $this->moduleContext->boardService->getBoard($srcBoardUID);
		$srcConfig = $srcBoard->loadBoardConfig();
		$srcBasePath = $srcBoard->getBoardUploadedFilesDirectory();
		$srcImgPath = $srcBasePath . $srcConfig['IMG_DIR'];
		$srcThumbPath = $srcBasePath . $srcConfig['THUMB_DIR'];
	
		foreach ($attachments as $file) {
			$imageFilename = $file['tim'] . $file['ext'];
			$thumbFilename = $this->moduleContext->FileIO->resolveThumbName($file['tim'], $srcBoard);
	
			$srcImage = $srcImgPath . $imageFilename;
			$destImage = $destImgPath . $imageFilename;
			$srcThumb = $srcThumbPath . $thumbFilename;
			$destThumb = $destThumbPath . $thumbFilename;
	
			// Copy/move the image file if it exists
			if (is_file($srcImage)) {
				if ($isCopy) {
					copy($srcImage, $destImage);
				} else {
					moveFileOnly($srcImage, $destImgPath);
				}
			}
	
			// Copy/move the thumbnail file if it exists
			if (is_file($srcThumb)) {
				if ($isCopy) {
					copy($srcThumb, $destThumb);
				} else {
					moveFileOnly($srcThumb, $destThumbPath);
				}
			}
		}
	}
	

	private function handleThreadMove($thread, $hostBoard, $destinationBoard, $leaveShadowThread = true) {
		// redirect for url
		$threadRedirectUrl = '';

		$threadData = $thread['thread'];
		$threadUid = $threadData['thread_uid'];

		// board uid of the destination board
		$destinationBoardUID = $destinationBoard->getBoardUID();

		// use thread redirection
		if($leaveShadowThread) { 
			// lock original thread and duplicate contents to destination board
			$newThreadUid = $this->copyThreadToBoard($threadUid, $destinationBoard);

			$newThread = $this->moduleContext->threadService->getThreadByUID($newThreadUid)['thread'];

			// leave shadow post
			$this->leavePostInShadowThread($threadData, $hostBoard, $newThread, $destinationBoard);
			
			// opening post
			$openingPost = $thread['posts'][0];

			// lock thread
			$openingPost['status'] = $this->toggleThreadStatus($openingPost, 'stop');

			// make unmoveable
			$openingPost['status'] = $this->toggleThreadStatus($openingPost, 'ghost');

			$threadRedirectUrl = $destinationBoard->getBoardThreadURL($newThread['post_op_number']); 
		} else {
			$attachments = $this->moduleContext->threadRepository->getAllAttachmentsFromThread($threadUid);

			$this->moduleContext->postRedirectService->addNewRedirect($hostBoard->getBoardUID(), $destinationBoard->getBoardUID(), $threadUid);

			$this->copyThreadAttachmentsToBoard($attachments, $destinationBoard);

			$this->moduleContext->threadService->moveThreadAndUpdate($threadUid, $destinationBoard);

			$this->moduleContext->quoteLinkService->moveQuoteLinksFromThread($threadUid, $destinationBoardUID);

			
			$threadRedirectUrl = $this->moduleContext->postRedirectService->resolveRedirectedThreadLinkFromThreadUID($threadUid);
		}

		// rebuild the boards' html
		$boardsToRebuild = [
			$hostBoard,
			$destinationBoard
		];
		rebuildBoardsByArray($boardsToRebuild);

		// return redirect
		return $threadRedirectUrl;
	}


	public function ModulePage() {
		// If form was submitted to move a thread
		if (!empty($_POST['move-thread-submit'])) {
			$thread_uid = $_POST['move-thread-uid'] ?? null;
			$destinationBoardUID = $_POST['radio-board-selection'] ?? null;
			$leaveShadowThread = !empty($_POST['leave-shadow-thread']);
	
			// Validate inputs
			if (empty($thread_uid)) {
				throw new BoardException("Invalid thread_uid from request");
			}
			if (empty($destinationBoardUID)) {
				throw new BoardException("Invalid board uid from request");
			}
	
			// Retrieve thread and validate
			$thread = $this->moduleContext->threadService->getThreadByUID($thread_uid);
			if (!$thread) {
				throw new BoardException("Thread not found");
			}

			$threadOP = $thread['posts'][0];
			$threadStatus = new FlagHelper($threadOP['status']);
	
			if ($threadStatus->value('ghost')) {
				throw new BoardException("Cannot move ghost threads");
			}
	
			// Get board objects
			$hostBoard = searchBoardArrayForBoard($thread['thread']['boardUID']);
			$destinationBoard = searchBoardArrayForBoard($destinationBoardUID);
	
			$redirectURL = '';
			$this->moduleContext->transactionManager->run(function () use (
				&$redirectURL,
				$thread,
				$hostBoard,
				$destinationBoard,
				$leaveShadowThread
				) {
				// Perform the move
				$redirectURL = $this->handleThreadMove(
					$thread,
					$hostBoard,
					$destinationBoard,
					$leaveShadowThread
				);
			});
	
			// Log the action
			$destinationBoardTitle = htmlspecialchars($destinationBoard->getBoardTitle());
			$this->moduleContext->actionLoggerService->logAction(
				"Moved thread {$thread['thread']['post_op_number']} to board $destinationBoardTitle",
				$hostBoard->getBoardUID()
			);
	
			redirect($redirectURL);
		}
	
		// Show move form if no submission
		$templateData = $this->prepareMoveFormTemplateValues();
		$threadMoveFormHtml = $this->moduleContext->adminPageRenderer->ParseBlock('THREAD_MOVE_FORM', $templateData);
	
		echo $this->moduleContext->adminPageRenderer->ParsePage(
			'GLOBAL_ADMIN_PAGE_CONTENT',
			['{$PAGE_CONTENT}' => $threadMoveFormHtml],
			true
		);
	}
	

	private function prepareMoveFormTemplateValues(): array {
		$thread_uid = $_GET['thread_uid'] ?? '';

		if (!$thread_uid) {
			throw new InvalidArgumentException("No thread uid selected");
		}
		$thread = $this->moduleContext->threadService->getThreadByUID($thread_uid)['thread'];
		$threadNumber = $this->moduleContext->threadRepository->resolveThreadNumberFromUID($thread_uid);
		$threadParentBoard = searchBoardArrayForBoard($thread['boardUID']);

		$boardRadioHTML = generateBoardListRadioHTML($threadParentBoard, GLOBAL_BOARD_ARRAY);

		return [
			'{$FORM_ACTION}' => $this->myPage,
			'{$THREAD_UID}' => htmlspecialchars($thread_uid),
			'{$THREAD_NUMBER}' => $threadNumber,
			'{$CURRENT_BOARD_UID}' => $threadParentBoard->getBoardUID(),
			'{$CURRENT_BOARD_NAME}' => htmlspecialchars($threadParentBoard->getBoardTitle()) . ' (' . $threadParentBoard->getBoardUID() . ')',
			'{$BOARD_RADIO_HTML}' => $boardRadioHTML,
		];
	}

	private function toggleThreadStatus(array $openingPost, string $flag): FlagHelper {
		// Create helper with current status
		$flags = new FlagHelper($openingPost['status']);

		// Toggle the specified flag
		$flags->toggle($flag);

		// Save the updated status back to the post
		$this->moduleContext->postRepository->setPostStatus($openingPost['post_uid'], $flags->toString());

		// Return updated flags
		return $flags;
	}
}
