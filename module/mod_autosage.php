<?php
// auto sage module made for kokonotsuba by deadking
class mod_autosage extends moduleHelper {
	private $mypage;
	private $LOCKICON = '';

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		
		$this->LOCKICON = $this->config['STATIC_URL'].'image/locked.png';
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Autosage Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $file, $thread_uid, $img, &$status) {
		$threadSingleton = threadSingleton::getInstance();
	
		if ($thread_uid) {
			$post = $threadSingleton->fetchPostsFromThread($thread_uid)[0];
			$fh = new FlagHelper($post['status']);
			if($fh->value('as')) $age = false;
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $threadPosts, $isReply) {
		$fh = new FlagHelper($post['status']);
		if($fh->value('as')) {
			$arrLabels['{$POSTINFO_EXTRA}'].=' <span class="autosage" title="Autosage. This thread cannot be bumped.">AS</span>';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();

		if ($roleLevel < $this->config['AuthLevels']['CAN_AUTO_SAGE']) return;
		$fh = new FlagHelper($post['status']);
		if(!$isres) $modfunc.= '<span class="adminAutosageFunction">[<a href="'.$this->mypage.'&thread_uid='.$post['thread_uid'].'"'.($fh->value('as')?' title="Allow age">as':' title="Autosage">AS').'</a>]</span>';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$threadSingleton = threadSingleton::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$globalHTML = new globalHTML($this->board);
		$softErrorHandler = new softErrorHandler($this->board);
		
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_AUTO_SAGE']);
		
		$post = $threadSingleton->fetchPostsFromThread(strval($_GET['thread_uid']))[0];
		if (!$PIO->isThreadOP($post['post_uid'])) $globalHTML->error('ERROR: Cannot autosage reply.');
		if (!$post) $globalHTML->error('ERROR: Post does not exist.');
		$flgh = $PIO->getPostStatus($post['post_uid']);
		$flgh->toggle('as');
		$PIO->setPostStatus($post['post_uid'], $flgh->toString());
		
		$logMessage = $flgh->value('as') ? "Autosaged No. {$post['no']}" : "Took off autosage on No. {$post['no']}";
		$actionLogger->logAction($logMessage, $this->board->getBoardUID());
		
		$this->board->rebuildBoard();	
		redirect('back', 1);
	}
}
