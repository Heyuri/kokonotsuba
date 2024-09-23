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
		$file.= '<nobr>[<label><input type="checkbox" name="anigif" id="anigif" value="on" />Animated GIF</label>]</nobr>';
	}

	public function autoHookRegistBeforeCommit(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $isReply, $imgWH, &$status) {
		$fh = new FlagHelper($status);

		$size =($dest && is_file($dest)) ? @getimagesize($dest) :[];

		if(isset($_POST['anigif']) && isset($size[2]) && ($size[2] == 1)) {
			$fh->toggle('agif');
			$status = $fh->toString();
		}
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();

		$fh = new FlagHelper($post['status']);
		if($FileIO->imageExists($post['tim'].$post['ext'])
		&& $fh->value('agif')) {
			$imgURL = $FileIO->getImageURL($post['tim'].$post['ext']);
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
			$modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'"'.($fh->value('agif')?' title="Use still image of GIF">g':' title="Use Animated GIF">G').'</a>]';
		}
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		if($AccountIO->valid() < $this->config['roles']['LEV_JANITOR']) {
			error('403 Access denied');
		}

		$post = $PIO->fetchPosts($_GET['no']);
		if(!count($post)) error('ERROR: Post does not exist.');
		if($post[0]['ext'] && $post[0]['ext'] == '.gif') {
			if(!$FileIO->imageExists($post[0]['tim'].$post[0]['ext'])) {
				error('ERROR: attachment does not exist.');
			}
			$flgh = $PIO->getPostStatus($post[0]['status']);
			$flgh->toggle('agif');
			$PIO->setPostStatus($post[0]['no'], $flgh->toString());
			$PIO->dbCommit();
			
			logtime('Changed anigif status on post No.'.$post['no'].' ('.($flgh->value('agif')?'true':'false').')', $AccountIO->valid());
			redirect('back', 0);
		} else {
			error('ERROR: Post does not have attechment.');
		}
	}
}
