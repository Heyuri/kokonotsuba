<?php
// thread stop module made for kokonotsuba by deadking
class mod_stop extends moduleHelper {
	private $mypage;
	private $LOCKICON = '';

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->LOCKICON = $this->config['STATIC_URL'].'/image/locked.png';
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Stop Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $file, $ip, $thread_uid) {
		$staffSession = new staffAccountFromSession;
		$globalHTML = new globalHTML($this->board);
		$roleLevel = $staffSession->getRoleLevel();
		

		if ($thread_uid && $roleLevel < $this->config['roles']['LEV_MODERATOR']) {
			$flags = getThreadStatus($thread_uid);

			if($flags->value('stop')) $globalHTML->error('ERROR: This thread is locked.');
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $threadPosts, $isReply) {
		$fh = new FlagHelper($post['status']);
		if ($fh->value('stop')) {
			$arrLabels['{$POSTINFO_EXTRA}'].='<img src="'.$this->LOCKICON.'" class="icon" width="16" height="16" title="Locked">';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isReply) {
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();
		
		if ($roleLevel < $this->config['AuthLevels']['CAN_LOCK']) return;
		$fh = new FlagHelper($post['status']);
		if(!$isReply) $modfunc.= '<span class="adminLockFunction">[<a href="'.$this->mypage.'&thread_uid='.$post['thread_uid'].'"'.($fh->value('stop')?' title="Unlock">l':' title="Lock thread">L').'</a>]</span>';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$threadSingleton = threadSingleton::getInstance();

		$actionLogger = ActionLogger::getInstance();
		$softErrorHandler = new softErrorHandler($this->board);
		$globalHTML = new globalHTML($this->board);
		
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_LOCK']);

		$post = $threadSingleton->fetchPostsFromThread(strval($_GET['thread_uid']))[0];
		if(!$PIO->isThreadOP($post['post_uid'])) $globalHTML->error('ERROR: Cannot lock reply.');
		if(!$post) $globalHTML->error('ERROR: Post does not exist.');
		$flgh = $PIO->getPostStatus($post['post_uid']);
		$flgh->toggle('stop');
		$PIO->setPostStatus($post['post_uid'], $flgh->toString());
		
		$logMessage = $flgh->value('stop') ? "Locked thread No. {$post['no']}" : "Unlock thread No. {$post['no']}";
		
		$actionLogger->logAction($logMessage, $this->board->getBoardUID());
		$this->board->rebuildBoard();
		redirect('back', 0);
	}
}
