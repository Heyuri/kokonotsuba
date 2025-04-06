<?php
// sticky module made for kokonotsuba by deadking
class mod_sticky extends moduleHelper {
	private $mypage;
	private $STICKYICON = '';

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		$this->STICKYICON = $this->config['STATIC_URL'].'image/sticky.png';
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Sticky Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PIOPDO::getInstance();
		$fh = new FlagHelper($post['status']);
		if ($fh->value('sticky')) {
			$arrLabels['{$POSTINFO_EXTRA}'].='<img src="'.$this->STICKYICON.'" class="icon" height="18" width="18" title="Sticky">';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;

		if ($staffSession->getRoleLevel() < $this->config['AuthLevels']['CAN_STICKY']) return;
		$fh = new FlagHelper($post['status']);
		if (!$isres) $modfunc.= '<span class="adminStickyFunction">[<a href="'.$this->mypage.'&post_uid='.$post['post_uid'].'"'.($fh->value('sticky')?' title="Unsticky">s':' title="Sticky post">S').'</a>]</span>';
	}
	
	public function autoHookRegistAfterCommit($lastno, $resto, $name, $email, $sub, $com) {
		$PIO = PIOPDO::getInstance();
		$threads = $PIO->getThreadListFromBoard($this->board);
		foreach ($threads as $thread) {
			$post = $PIO->fetchPostsFromThread($thread)[0];
			$flgh = $PIO->getPostStatus($post['post_uid']);
			if ($flgh->value('sticky')) $PIO->bumpThread($thread,true);
		}
	}
	
	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$staffSession = new staffAccountFromSession;
		$softErrorHandler = new softErrorHandler($this->board);
		$actionLogger = ActionLogger::getInstance();
		$globalHTML = new globalHTML($this->board);
		
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_STICKY']);

		$post = $PIO->fetchPosts(($_GET['post_uid']))[0];
		if(!$PIO->isThreadOP($post['post_uid'])) $globalHTML->error('ERROR: Cannot sticky reply.');
		if(!$post) $globalHTML->error('ERROR: Post does not exist.');
		$flgh = $PIO->getPostStatus($post['post_uid']);
		$flgh->toggle('sticky');
		$PIO->setPostStatus($post['post_uid'], $flgh->toString());
		
		//reset its bump number to the last reply's increment
		if (!$flgh->value('sticky')) $PIO->bumpThread($post['thread_uid']);

		$actionLogger->logAction('Changed sticky status on post No.'.$post['no'].' ('.($flgh->value('sticky')?'true':'false').')', $this->board->getBoardUID());
		$this->board->rebuildBoard();
		redirect('back', 1);
	}
}
