<?php
// sticky module made for kokonotsuba by deadking
class mod_sticky extends ModuleHelper {
	private $mypage;
	private $STICKYICON = STATIC_URL.'/image/sticky.png';

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Sticky Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PMCLibrary::getPIOInstance();
		$fh = new FlagHelper($post['status']);
		if ($fh->value('sticky')) {
			$arrLabels['{$POSTINFO_EXTRA}'].='&nbsp;<img src="'.$this->STICKYICON.'" class="icon" title="Sticky" />';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		if ($AccountIO->valid() < LEV_MODERATOR) return;
		$fh = new FlagHelper($post['status']);
		if (!$isres) $modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'"'.($fh->value('sticky')?' title="Unsticky">s':' title="Sticky post">S').'</a>]';
	}
	
	public function autoHookRegistAfterCommit($lastno, $resto, $name, $email, $sub, $com) {
		$PIO = PMCLibrary::getPIOInstance();
		$threads = $PIO->fetchThreadList();
		foreach ($threads as $thread) {
			$post = $PIO->fetchPosts($thread)[0];
			$flgh = $PIO->getPostStatus($post['status']);
			if ($flgh->value('sticky')) $PIO->bumpThread($thread,true);
		}
		$PIO->dbCommit();
	}
	
	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();

		if ($AccountIO->valid() < LEV_MODERATOR) {
			error('403 Access denied');
		}

		$post = $PIO->fetchPosts(intval($_GET['no']))[0];
		if(!$PIO->isThread($post['no'])) error('ERROR: Cannot sticky reply.');
		if(!$post) error('ERROR: Post does not exist.');
		$flgh = $PIO->getPostStatus($post['status']);
		$flgh->toggle('sticky');
		$PIO->setPostStatus($post['no'], $flgh->toString());
		$PIO->dbCommit();
		
		$level = $AccountIO->valid();
		$moderatorUsername = $AccountIO->getUsername();
		$moderatorLevel = $AccountIO->getRoleLevel();
		logtime('Changed sticky status on post No.'.$post['no'].' ('.($flgh->value('sticky')?'true':'false').')', $moderatorUsername);
		updatelog();
		redirect('back', 1);
	}
}
