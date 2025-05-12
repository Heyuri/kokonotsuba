<?php
// admin extra module made for kokonotsuba by deadking
class mod_admindel extends moduleHelper {
	private $BANFILE = '';
	private $JANIMUTE_LENGTH = '';
	private $JANIMUTE_REASON = '';
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->BANFILE = $this->board->getBoardStoragePath() . 'bans.log.txt';
		$this->JANIMUTE_LENGTH = $this->config['ModuleSettings']['JANIMUTE_LENGTH'];
		$this->JANIMUTE_REASON = $this->config['ModuleSettings']['JANIMUTE_REASON'];
		
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Admin Deletion';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$FileIO = PMCLibrary::getFileIOInstance();
		$staffSession = new staffAccountFromSession;
		
		if ($staffSession->getRoleLevel() < $this->config['AuthLevels']['CAN_DELETE_POST']) return;
		
		$postBoard = searchBoardArrayForBoard($this->moduleBoardList, $post['boardUID']);

		$modfunc.= '<span class="adminFunctions adminDeleteFunction">[<a href="'.$this->mypage.'&action=del&post_uid='.$post['post_uid'].'&excimer=1" title="Delete">D</a>]</span>';
		if ($post['ext'] && $FileIO->imageExists($post['tim'].$post['ext'], $postBoard)) $modfunc.= '<span class="adminFunctions adminDeleteFileFunction">[<a href="'.$this->mypage.'&action=imgdel&post_uid='.$post['post_uid'].'" title="Delete file">DF</a>]</span>';
		$modfunc.= '<span class="adminFunctions adminDeleteMuteFunction">[<a href="'.$this->mypage.'&action=delmute&post_uid='.$post['post_uid'].'" title="Delete and mute for '.$this->JANIMUTE_LENGTH.' minute'.($this->JANIMUTE_LENGTH == 1 ? "" : "s").'">DM</a>]</span>';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();

		$boardIO = boardIO::getInstance();
		$ActionLogger = ActionLogger::getInstance();
		$globalHTML = new globalHTML($this->board);
		$softErrorHandler = new softErrorHandler($globalHTML);

		$threadSingleton = threadSingleton::getInstance();
		
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_DELETE_POST']);
		
		$post = $PIO->fetchPosts(strval($_GET['post_uid']??''))[0];
		$board = $boardIO->getBoardByUID($post['boardUID']);
		$boardUID = $board->getBoardUID();

		if (!$post) $globalHTML->error('ERROR: That post does not exist.');
		$files = false;
		switch ($_GET['action']??'') {
			case 'del':
				$this->moduleEngine->useModuleMethods('PostOnDeletion', array($post['post_uid'], 'backend'));
				$files = $PIO->removePosts(array($post['post_uid']));
				$ActionLogger->logAction('Deleted post No.'.$post['no'], $boardUID);
				break;
			case 'delmute':
				$this->moduleEngine->useModuleMethods('PostOnDeletion', array($post['post_uid'], 'backend'));
				$files = $PIO->removePosts(array($post['post_uid']));
				$ip = $post['host'];
				$starttime = $_SERVER['REQUEST_TIME'];
				$expires = $starttime+intval($this->JANIMUTE_LENGTH)*60;
				$f = fopen($this->BANFILE, 'w');
				if ($ip) {
					$reason = $this->JANIMUTE_REASON;
					fwrite($f, "$ip,$starttime,$expires,$reason\r\n");
				}
				fclose($f);
				$ActionLogger->logAction('Muted '.$ip.' and deleted post No.'.$post['no'], $boardUID);
				break;
			case 'imgdel':
				$files = $PIO->removeAttachments(array($post['post_uid']));
				$ActionLogger->logAction('Deleted file for post No.'.$post['no'], $boardUID);
				break;
			default:
				$globalHTML->error('ERROR: Invalid action.');
				break;
		}
		if ($files) {
			$FileIO->deleteImage($files, $board);
		}
		
		deleteThreadCache($post['thread_uid']);

		// if its a thread, rebuild all board pages
		if($post['is_op']) {
			$this->board->rebuildBoard();
		} else {
			// otherwise just rebuild the page the reply is on
			$thread_uid = $post['thread_uid'];

			$threads = $threadSingleton->getThreadListFromBoard($this->board);

			$pageToRebuild = getPageOfThread($thread_uid, $threads, $this->config['PAGE_DEF']);
			
			$this->board->rebuildBoardPage($pageToRebuild);
		}
		redirect('back', 0);
	}
}
