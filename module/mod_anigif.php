<?php
// animated gif module made for kokonotsuba by deadking
// "forked" from the siokara mod for pixmicat
class mod_anigif extends moduleHelper {
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		$this->mypage = $this->getModulePageURL();
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

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $file, $isReply, $imgWH, &$status) {
		$mimeType = $file->getMimeType();
		
		if($mimeType !== 'image/gif') {
			return;
		}
		
		$anigifRequested = isset($_POST['anigif']);
		
		$flagHelper = new FlagHelper($status);
		if ($anigifRequested) {
			$flagHelper->toggle('agif');
			$status = $flagHelper->toString();
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $threadPosts, $isReply) {
		$FileIO = PMCLibrary::getFileIOInstance();

		$fh = new FlagHelper($post['status']);
		if($fh->value('agif')) {
			// check if the file exists in here so time isn't wasted with checking if the file exists
			if(!$FileIO->imageExists($post['tim'].$post['ext'], $this->board)) {
				return;
			}
			
			$imgURL = $FileIO->getImageURL($post['tim'].$post['ext'], $this->board);
			$arrLabels['{$IMG_SRC}'] = preg_replace('/<img src=".*"/U','<img src="'.$imgURL.'"',$arrLabels['{$IMG_SRC}']);
			$arrLabels['{$IMG_BAR}'].= '<span class="animatedGIFLabel imageOptions">[Animated GIF]</span>';
		}
	}

	public function autoHookThreadReply(&$arrLabels, $post, $threadPosts, $isReply){
		$this->autoHookThreadPost($arrLabels, $post, $threadPosts, $isReply);
	}
	
	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$fh = new FlagHelper($post['status']);
		if ($post['ext'] == '.gif') {
			$modfunc.= '<span class="adminFunctions adminGIFFunction">[<a href="'.$this->mypage.'&post_uid='.$post['post_uid'].'"'.($fh->value('agif')?' title="Use still image of GIF">g':' title="Use animated GIF">G').'</a>]</span>';
		}
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		$actionLogger = ActionLogger::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$softErrorHandler = new softErrorHandler($this->board);
		$globalHTML = new globalHTML($this->board);
		
		$softErrorHandler->handleAuthError($this->config['roles']['LEV_JANITOR']);

		$post = $PIO->fetchPosts($_GET['post_uid'] ?? 0)[0];
		if(!count($post)) $globalHTML->error('ERROR: Post does not exist.');
		if($post['ext'] && $post['ext'] == '.gif') {
			if(!$FileIO->imageExists($post['tim'].$post['ext'], $this->board)) {
				$globalHTML->error('ERROR: attachment does not exist.');
			}
			$flgh = new FlagHelper($post['status']);
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
