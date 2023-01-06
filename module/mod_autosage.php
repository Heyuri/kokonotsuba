<?php
// auto sage module made for kokonotsuba by deadking
class mod_autosage extends ModuleHelper {
	private $mypage;
	private $LOCKICON = STATIC_URL.'/image/locked.png';

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Autosage Threads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $resto, $img, &$status) {
		$PIO = PMCLibrary::getPIOInstance();
		if ($resto) {
			$post = $PIO->fetchPosts($resto)[0];
			$fh = new FlagHelper($post['status']);
			if($fh->value('as')) $age = false;
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PMCLibrary::getPIOInstance();
		$fh = new FlagHelper($post['status']);
		if($fh->value('as')) {
			$arrLabels['{$POSTINFO_EXTRA}'].='&nbsp;<b title="AutoSage"><font color="#F00">AS</font></b>';
		}
	}

	public function autoHookAdminList(&$modfunc, $post, $isres) {
		if (valid() < LEV_MODERATOR) return;
		$fh = new FlagHelper($post['status']);
		if(!$isres) $modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'"'.($fh->value('as')?' title="Allow age">as':' title="Autosage">AS').'</a>]';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();

		if (valid() < LEV_MODERATOR) {
			error('403 Access denied');
		}

		$post = $PIO->fetchPosts(intval($_GET['no']))[0];
		if (!$PIO->isThread($post['no'])) error('ERROR: Cannot autosage reply.');
		if (!$post) error('ERROR: Post does not exist.');
		$flgh = $PIO->getPostStatus($post['status']);
		$flgh->toggle('as');
		$PIO->setPostStatus($post['no'], $flgh->toString());
		$PIO->dbCommit();

		logtime('Changed autosage status on post No.'.$post['no'].' ('.($flgh->value('as')?'true':'false').')', valid());
		updatelog();
		redirect('back', 1);
	}
}
