<?php
class mod_api extends ModuleHelper {
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = str_replace('&amp;', '&', $this->getModulePageURL());
	}

	public function getModuleName() {
		return __CLASS__.' : K! JSON API';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function ModulePage() {
		$PIO =  PIOPDO::getInstance();
		header('Content-Type: application/json');
		$no = intval($_GET['no']??'');

		$posts = [];
		$post_uid = $PIO->resolvePostUidFromPostNumber($this->board, $no);
		if ($no) {
			if($PIO->isThreadOP($post_uid)) $posts = $PIO->fetchPostsFromThread($post_uid);
			$posts = $PIO->fetchPosts($post_uid);
		} else {
			$posts = $PIO->getPostsFromBoard($this->board);
		}

		for ($i=0; $i<count($posts); $i++) {
			unset($posts[$i]['pwd']);
			unset($posts[$i]['host']);
		}

		$dat = json_encode($posts,  JSON_PRETTY_PRINT);
		echo $dat;
	}
}
