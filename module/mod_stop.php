<?php
// thread stop module made for kokonotsuba by deadking
class mod_stop extends ModuleHelper {
	private $mypage;
	private $LOCKICON = STATIC_URL.'/image/locked.png';

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Stop Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $file, $ip, $resto) {
		$PIO = PMCLibrary::getPIOInstance();

		if ($resto && $PIO->isThread($resto)) {
			$post = $PIO->fetchPosts($resto)[0];
			$fh = new FlagHelper($post['status']);
			if($fh->value('stop')) error('ERROR: This thread is locked.');
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PMCLibrary::getPIOInstance();
		$fh = new FlagHelper($post['status']);
		if ($fh->value('stop')) {
			$arrLabels['{$POSTINFO_EXTRA}'].='&nbsp;<img src="'.$this->LOCKICON.'" class="icon" title="Locked" />';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$AccountIO = PMCLibrary::getAccountIOInstance();
		if ($AccountIO->valid() < LEV_MODERATOR) return;
		$fh = new FlagHelper($post['status']);
		if(!$isres) $modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'"'.($fh->value('stop')?' title="Unlock">l':' title="Lock thread">L').'</a>]';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		if ($AccountIO->valid() < LEV_MODERATOR) {
			error('403 Access denied');
		}

		$post = $PIO->fetchPosts(intval($_GET['no']))[0];
		if(!$PIO->isThread($post['no'])) error('ERROR: Cannot lock reply.');
		if(!$post) error('ERROR: Post does not exist.');
		$flgh = $PIO->getPostStatus($post['status']);
		$flgh->toggle('stop');
		$PIO->setPostStatus($post['no'], $flgh->toString());
		$PIO->dbCommit();

		logtime('Changed lock status on post No.'.$post['no'].' ('.($flgh->value('stop')?'true':'false').')', $AccountIO->valid());
		updatelog();
		redirect('back', 0);
	}
}
