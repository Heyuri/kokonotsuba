<?php
class mod_stat extends moduleHelper {
	private $mypage;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		$this->mypage = str_replace('&amp;', '&', $this->getModulePageURL());
	}

	public function getModuleName() {
		return __CLASS__.' : K! Stats';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function ModulePage() {
		$PIO = PIOPDO::getInstance();
		header('Content-Type: text/plain');
		switch ($_GET['stats']??'') {
			case 'alltime': $limit = -1; break;
			case 'lastpost': $limit = -2; break;
			case 'ppd': $limit = 60*60*24; break;
			case 'pph':
			default: $limit = 60*60; break;
		}
		$posts = $PIO->getPostsFromBoard($this->board);
		$time = $_SERVER['REQUEST_TIME'] - $limit;
		$tim = intval($time.substr($_SERVER['REQUEST_TIME_FLOAT'],2,3));
		$count = 0;
		if ($limit == -2) {
			$count = $posts[0]['no'];
		} else {
			foreach ($posts as $post) {
				$ptim = intval($post['tim']);
				if (($ptim>$tim) || ($limit == -1)) $count++;
			}
		}
		die(strval($count));
	}
}
