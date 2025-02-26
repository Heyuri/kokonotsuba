<?php
// animated gif module made for kokonotsuba by deadking
// "forked" from the siokara mod for pixmicat
class mod_anigif extends ModuleHelper {
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.' : K! Animated GIF';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookPostFormFile(&$file){
		$file.= '<div id="anigifContainer"><label id="anigifLabel" title="Makes GIF thumbnails animated"><input type="checkbox" name="anigif" id="anigif" value="on">Animated GIF</label></div>';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $isReply, $imgWH, &$status) {
		$fh = new FlagHelper($status);

		$size =($dest && is_file($dest)) ? getimagesize($dest) :[];

		if(isset($_POST['anigif']) && isset($size[2]) && ($size[2] == 1)) {
			$fh->toggle('agif');
			$status = $fh->toString();
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PIOPDO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();

		$fh = new FlagHelper($post['status']);
		if($FileIO->imageExists($post['tim'].$post['ext'], $this->board)
		&& $fh->value('agif')) {
			$imgURL = $FileIO->getImageURL($post['tim'].$post['ext'], $this->board);
			$arrLabels['{$IMG_SRC}'] = preg_replace('/<img src=".*"/U','<img src="'.$imgURL.'"',$arrLabels['{$IMG_SRC}']);
			$arrLabels['{$IMG_BAR}'].= '<small>[Animated GIF]</small>';
		}
	}

	public function autoHookThreadReply(&$arrLabels, $post, $isReply){
		$this->autoHookThreadPost($arrLabels, $post, $isReply);
	}
	
	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$fh = new FlagHelper($post['status']);
		if ($post['ext'] == '.gif') {
			$modfunc.= '[<a href="'.$this->mypage.'&thread_uid='.$post['thread_uid'].'"'.($fh->value('agif')?' title="Use still image of GIF">g':' title="Use Animated GIF">G').'</a>]';
		}
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$staffSession = new staffAccountFromSession;
		$softErrorHandler = new softErrorHandler($this->board);
		$roleLevel = $staffSession->getRoleLevel();
		
		$softErrorHandler->handleAuthError($this->config['roles']['LEV_JANITOR']);

		$post = $PIO->fetchPostsFromThread($_GET['thread_uid'])[0];
		if(!count($post)) $globalHTML->error('ERROR: Post does not exist.');
		if($post['ext'] && $post['ext'] == '.gif') {
			if(!$FileIO->imageExists($post['tim'].$post['ext'], $this->board)) {
				$globalHTML->error('ERROR: attachment does not exist.');
			}
			$flgh = $PIO->getPostStatus($post['post_uid']);
			$flgh->toggle('agif');
			$PIO->setPostStatus($post['post_uid'], $flgh->toString());
			
			$logMessage = $flgh->value('agif') ? "Animated gif activated on No. {$post['no']}" : "Animated gif taken off of No. {$post['no']}";
			$actionLogger->logAction($logMessage, $this->board->getBoardUID());
			
			redirect('back', 0);
		} else {
			$globalHTML->error('ERROR: Post does not have attechment.');
		}
	}
}
