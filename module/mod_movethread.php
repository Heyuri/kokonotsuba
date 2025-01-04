<?php
//move thread module
class mod_movethread extends ModuleHelper {
	private $mypage;

	
	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}
	
	public function getModuleName() {
		return __CLASS__.' : Move Thread';
	}
	
	public function getModuleVersionInfo() {
		return 'Kokonotsuba';
	}
	
	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		if ($staffSession->getRoleLevel() < $this->config['roles']['LEV_MODERATOR']) return;

		if (!$isres) $modfunc .= '[<a href="'.$this->mypage.'&thread_uid='.$post['thread_uid'].'" title="move thread">MT</a>]';
	}

	private function copyThreadFilesFromHostToDestination($filesToCopy, $hostBoard, $destinationBoard) {
		$FileIO = PMCLibrary::getFileIOInstance();
		$boardIO = boardIO::getInstance();

		$hostBoardStoredFilesDir = $hostBoard->getBoardUploadedFilesDirectory();
		$destinationBoardStoredFilesDir = $destinationBoard->getBoardUploadedFilesDirectory();
		$hostBoardConfig = $hostBoard->loadBoardConfig();
		$destinationBoardConfig = $destinationBoard->loadBoardConfig();

		$destinationBoardImgPath = $destinationBoardStoredFilesDir.$destinationBoardConfig['IMG_DIR'];
		$destinationBoardThumbPath = $destinationBoardStoredFilesDir.$destinationBoardConfig['THUMB_DIR'];

		foreach($filesToCopy as $fileToCopy) {
			$fileBoard = $boardIO->getBoardByUID($fileToCopy['boardUID']);
			$fileBoardConfig = $fileBoard->loadBoardConfig();

			$boardFullImgPath = $fileBoard->getBoardUploadedFilesDirectory().$fileBoardConfig['IMG_DIR'];
			$boardFullThumbPath = $fileBoard->getBoardUploadedFilesDirectory().$fileBoardConfig['THUMB_DIR'];
			$thumbName = $FileIO->resolveThumbName($fileToCopy['tim'], $fileBoard);

			moveFileOnly($boardFullImgPath.$fileToCopy['tim'].$fileToCopy['ext'], $destinationBoardImgPath);
			moveFileOnly($boardFullThumbPath.$thumbName, $destinationBoardThumbPath);
		}

	}
	

	private function handleThreadMove($thread, $hostBoard, $destinationBoard) {
		$PIO = PIOPDO::getInstance();
		$postRedirectIO = postRedirectIO::getInstance();
		
		$thread_uid = $thread['thread_uid'];
		$postsFromThread = $PIO->getPostsFromThread($thread_uid);
		$filesToCopy = $PIO->getAllAttachmentsFromThread($thread_uid);

		$this->copyThreadFilesFromHostToDestination($filesToCopy, $hostBoard, $destinationBoard);
		$PIO->moveThreadAndUpdate($thread_uid, $hostBoard, $destinationBoard);
		$PIO->bumpThread($thread_uid);
		$postRedirectIO->addNewRedirect($hostBoard->getBoardUID(), $destinationBoard->getBoardUID(), $thread_uid);

	}
	
	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$postRedirectIO = postRedirectIO::getInstance();
		$boardIO = boardIO::getInstance();
		
		$staffSession = new staffAccountFromSession;
		$softErrorHandler = new softErrorHandler($this->board);
		$globalHTML = new globalHTML($this->board);
		
		$softErrorHandler->handleAuthError($this->config['roles']['LEV_MODERATOR']);

		if(!empty($_POST['move-thread-submit'])) {
			$thread_uid = $_POST['move-thread-uid'] ?? null;
			$destinationBoardUID = $_POST['radio-board-selection'] ?? null;
			if(!$thread_uid) $globalHTML->error("Invalid thread_uid from request");
			if(!$destinationBoardUID) $globalHTML->error("Invalid board uid from request");

			$thread = $PIO->getThreadByUID($thread_uid);
			$hostBoardUID = $thread['boardUID'];

			$destinationBoard = $boardIO->getBoardByUID($destinationBoardUID);
			$hostBoard = $boardIO->getBoardByUID($hostBoardUID);
			
			$this->handleThreadMove($thread, $hostBoard, $destinationBoard);

			$destinationBoardTitle = htmlspecialchars($destinationBoard->getBoardTitle());
			$actionLogger->logAction("Moved thread {$thread['post_op_number']} to board $destinationBoardTitle", $this->board->getBoardUID());

			$redirectURL = $postRedirectIO->resolveRedirectedThreadLinkFromThreadUID($thread_uid); 
			redirect($redirectURL);
		} else {
			$htmlOutput = '';

			$globalHTML->head($htmlOutput);
			$htmlOutput .= $globalHTML->generateAdminLinkButtons();
			$globalHTML->drawAdminTheading($htmlOutput, $staffSession);
			$globalHTML->drawThreadMoveForm($htmlOutput, $this->mypage);
			$globalHTML->foot($htmlOutput);

			echo $htmlOutput;
		}
	}
}