<?php
//move thread module
class mod_movethread extends moduleHelper {
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__ . ' : Move Thread';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba';
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		if ($staffSession->getRoleLevel() < $this->config['roles']['LEV_MODERATOR']) return;

		if (!$isres) {
			$modfunc .= '<span class="adminMoveThreadFunction">[<a href="' . $this->mypage . '&thread_uid=' . $post['thread_uid'] . '" title="move thread">MT</a>]</span>';
		}
	}

	private function leavePostInShadowThread(array $originalThread, IBoard $originalBoard, array $newThread, IBoard $destinationBoard, bool $isAnon) {
		$PIO = PIOPDO::getInstance();
		$globalHTML = new globalHTML($originalBoard);
		$staffSession = new staffAccountFromSession();
		$tripcodeProcessor = new tripcodeProcessor($this->config, $globalHTML, $staffSession);
		$postDateFormatter = new postDateFormatter($this->config);
		
		$time = $_SERVER['REQUEST_TIME'];
		$now = $postDateFormatter->format($time);

		// Generate new post number
		$no = $originalBoard->getLastPostNoFromBoard() + 1;

		// Determine name and capcode
		$roleLevel = $staffSession->getRoleLevel();
		$capcodeRole = $globalHTML->roleNumberToRoleName($roleLevel);
		$username = $staffSession->getUsername();

		$nameToInsert = "$username ## $capcodeRole";

		// Anonymize staff as an anonymous moderator
		if ($isAnon) {
			$username = $originalBoard->loadBoardConfig()['DEFAULT_NONAME'];
			$modCapcodeRole = $globalHTML->roleNumberToRoleName($this->config['roles']['LEV_MODERATOR']);
			$nameToInsert = "$username ## $modCapcodeRole";
		}

		// Generate link to the new thread
		$newThreadUrl = $destinationBoard->getBoardThreadURL($newThread['post_op_number']);
		$moveComment = 'Thread moved to <a href="' . $newThreadUrl . '">'.$destinationBoard->getBoardTitle().'</a>';

		// Prepare post metadata
		$email = '';
		$dest = '';
		$ip = new IPAddress();

		// Apply tripcode/capcode
		$tripcodeProcessor->apply($nameToInsert, $email, $dest);

		// Get original thread UID
		$originalThreadUid = $originalThread['thread_uid'];

		// Add shadow post
		$PIO->addPost(
			$originalBoard,
			$no,
			$originalThreadUid,
			'', '', 0, '', '',
			0, 0, 0, 0, 0, 0, $now,
			$nameToInsert,
			'', '', $moveComment,
			$ip,
			false,
			$now,
		);
	}


	public function copyThreadToBoard(string $originalThreadUid, IBoard $destinationBoard): string {
		$threadSingleton = threadSingleton::getInstance();
		// Step 1: Get attachments
		$filesToCopy = $threadSingleton->getAllAttachmentsFromThread($originalThreadUid);
	
		// Step 2: Copy the thread data
		$newThreadUid = $threadSingleton->copyThreadAndPosts($originalThreadUid, $destinationBoard);
	
		// Step 3: Copy attachments
		$this->copyThreadAttachmentsToBoard($filesToCopy, $destinationBoard, true);
		
		return $newThreadUid;
	}
	
	private function copyThreadAttachmentsToBoard(array $attachments, IBoard $destinationBoard, bool $isCopy = false): void {
		if (empty($attachments)) return;
	
		$boardIO = boardIO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
	
		// Destination board paths and config
		$destBasePath = $destinationBoard->getBoardUploadedFilesDirectory();
		$destConfig = $destinationBoard->loadBoardConfig();
	
		$destImgPath = $destBasePath . $destConfig['IMG_DIR'];
		$destThumbPath = $destBasePath . $destConfig['THUMB_DIR'];
	
		// All attachments are from the same source board
		$srcBoardUID = $attachments[0]['boardUID'];
		$srcBoard = $boardIO->getBoardByUID($srcBoardUID);
		$srcConfig = $srcBoard->loadBoardConfig();
	
		$srcBasePath = $srcBoard->getBoardUploadedFilesDirectory();
		$srcImgPath = $srcBasePath . $srcConfig['IMG_DIR'];
		$srcThumbPath = $srcBasePath . $srcConfig['THUMB_DIR'];
	
		foreach ($attachments as $file) {
			$imageFilename = $file['tim'] . $file['ext'];
			$thumbFilename = $FileIO->resolveThumbName($file['tim'], $srcBoard);
	
			$srcImage = $srcImgPath . $imageFilename;
			$destImage = $destImgPath . $imageFilename;
	
			$srcThumb = $srcThumbPath . $thumbFilename;
			$destThumb = $destThumbPath . $thumbFilename;
	
			if ($isCopy) {
				copy($srcImage, $destImage);
				copy($srcThumb, $destThumb);
			} else {
				moveFileOnly($srcImage, $destImgPath);
				moveFileOnly($srcThumb, $destThumbPath);
			}
		}
	}	

	private function handleThreadMove($thread, $hostBoard, $destinationBoard, $leaveShadowThread = true, $isAnon = false) {
		$threadSingleton = threadSingleton::getInstance();
		$postRedirectIO = postRedirectIO::getInstance();

		// redirect for url
		$threadRedirectUrl = '';

		$threadUid = $thread['thread_uid'];

		// use thread redirection
		if($leaveShadowThread) { 
			// lock original thread and duplicate contents to destination board
			$newThreadUid = $this->copyThreadToBoard($threadUid, $destinationBoard);

			$newThread = $threadSingleton->getThreadByUID($newThreadUid);

			// leave shadow post
			$this->leavePostInShadowThread($thread, $hostBoard, $newThread, $destinationBoard, $isAnon);
			lockThread($thread);
			
			$threadRedirectUrl = $destinationBoard->getBoardThreadURL($newThread['post_op_number']); 
		} else {
			$attachments = $threadSingleton->getAllAttachmentsFromThread($threadUid);

			$postRedirectIO->addNewRedirect($hostBoard->getBoardUID(), $destinationBoard->getBoardUID(), $threadUid);
			$this->copyThreadAttachmentsToBoard($attachments, $destinationBoard);
			$threadSingleton->moveThreadAndUpdate($threadUid, $hostBoard, $destinationBoard);
			
			$threadRedirectUrl = $postRedirectIO->resolveRedirectedThreadLinkFromThreadUID($threadUid);
		}

		// rebuild the boards' html
		$boardsToRebuild = [
			$hostBoard->getBoardUID(),
			$destinationBoard->getBoardUID()
		];
		rebuildBoardsByUIDs($boardsToRebuild);

		// return redirect
		return $threadRedirectUrl;
	}


	public function ModulePage() {
		$threadSingleton = threadSingleton::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$postRedirectIO = postRedirectIO::getInstance();
		$boardIO = boardIO::getInstance();

		$softErrorHandler = new softErrorHandler($this->board);
		$globalHTML = new globalHTML($this->board);

		$softErrorHandler->handleAuthError($this->config['roles']['LEV_MODERATOR']);

		if (!empty($_POST['move-thread-submit'])) {
			$thread_uid = $_POST['move-thread-uid'] ?? null;
			$destinationBoardUID = $_POST['radio-board-selection'] ?? null;
			$isAnon = $_POST['is-anon'] ?? false;
			$leaveShadowThread = $_POST['leave-shadow-thread'] ?? false;

			if (!$thread_uid) $globalHTML->error("Invalid thread_uid from request");
			if (!$destinationBoardUID) $globalHTML->error("Invalid board uid from request");

			$thread = $threadSingleton->getThreadByUID($thread_uid);
			$hostBoard = $boardIO->getBoardByUID($thread['boardUID']);
			$destinationBoard = $boardIO->getBoardByUID($destinationBoardUID);

			$redirectURL = $this->handleThreadMove($thread, $hostBoard, $destinationBoard, $leaveShadowThread, $isAnon);

			$destinationBoardTitle = htmlspecialchars($destinationBoard->getBoardTitle());
			$actionLogger->logAction("Moved thread {$thread['post_op_number']} to board $destinationBoardTitle", $this->board->getBoardUID());

			redirect($redirectURL);
		} else {

			$templateData = $this->prepareMoveFormTemplateValues();
			$threadMoveFormHtml = $this->adminPageRenderer->ParseBlock('THREAD_MOVE_FORM', $templateData);
			echo $this->adminPageRenderer->ParsePage('GLOBAL_ADMIN_PAGE_CONTENT', ['{$PAGE_CONTENT}' => $threadMoveFormHtml], true);
		}
	}

	private function prepareMoveFormTemplateValues(): array {
		$globalHTML = new globalHTML($this->board);
		$threadSingleton = threadSingleton::getInstance();
		$boardIO = boardIO::getInstance();

		$thread_uid = $_GET['thread_uid'] ?? '';

		if (!$thread_uid) {
			$globalHTML->error("No thread uid selected");
		}
		$thread = $threadSingleton->getThreadByUID($thread_uid);
		$threadNumber = $threadSingleton->resolveThreadNumberFromUID($thread_uid);
		$threadParentBoard = $boardIO->getBoardByUID($thread['boardUID']);

		$boardRadioHTML = $globalHTML->generateBoardListRadioHTML($threadParentBoard);

		return [
			'{$FORM_ACTION}' => $this->mypage,
			'{$THREAD_UID}' => htmlspecialchars($thread_uid),
			'{$THREAD_NUMBER}' => $threadNumber,
			'{$CURRENT_BOARD_UID}' => $threadParentBoard->getBoardUID(),
			'{$CURRENT_BOARD_NAME}' => htmlspecialchars($threadParentBoard->getBoardTitle()) . ' (' . $threadParentBoard->getBoardUID() . ')',
			'{$BOARD_RADIO_HTML}' => $boardRadioHTML,
		];
	}
}
