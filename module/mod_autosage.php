<?php
// auto sage module made for kokonotsuba by deadking
class mod_autosage extends ModuleHelper {
	private $mypage;
	private $LOCKICON = '';

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->LOCKICON = $this->config['STATIC_URL'].'image/locked.png';
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Autosage Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $thread_uid, $img, &$status) {
		$PIO = PIOPDO::getInstance();
	
		if ($thread_uid) {
			$post = $PIO->fetchPostsFromThread($thread_uid)[0];
			$fh = new FlagHelper($post['status']);
			if($fh->value('as')) $age = false;
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$fh = new FlagHelper($post['status']);
		if($fh->value('as')) {
			$arrLabels['{$POSTINFO_EXTRA}'].='&nbsp;<b title="AutoSage"><font color="#F00">AS</font></b>';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();

		if ($roleLevel < $this->config['AuthLevels']['CAN_AUTO_SAGE']) return;
		$fh = new FlagHelper($post['status']);
		if(!$isres) $modfunc.= '[<a href="'.$this->mypage.'&thread_uid='.$post['thread_uid'].'"'.($fh->value('as')?' title="Allow age">as':' title="Autosage">AS').'</a>]';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$globalHTML = new globalHTML($this->board);
		$softErrorHandler = new softErrorHandler($this->board);
		$staffSession = new staffAccountFromSession;
		$roleLevel = $staffSession->getRoleLevel();
		
		$softErrorHandler->handleAuthError($this->config['AuthLevels']['CAN_AUTO_SAGE']);
		
		$post = $PIO->fetchPostsFromThread(strval($_GET['thread_uid']))[0];
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
