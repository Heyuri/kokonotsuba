<?php
class mod_SearchCategory extends ModuleHelper {
	private $mypage;
	private $THUMB_EXT = -1;

	public function __construct($PMS) {
		parent::__construct($PMS);
		
		$this->THUMB_EXT = $this->config['THUMB_SETTING']['Format'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return __CLASS__.' : K! Category Search';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}

	public function autoHookToplink(&$linkbar, $isreply){
		$linkbar .= ' [<a href="'.$this->mypage.'">Search Category</a>] ';
	}

	public function ModulePage(){
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$PTE = PMCLibrary::getPTEInstance();
		$PMS = PMCLibrary::getPMSInstance();
		$AccountIO = PMCLibrary::getAccountIOInstance();
		
		$category = isset($_GET['c']) ? strtolower(strip_tags(trim($_GET['c']))) : ''; // Search for category tags
		if(!$category) error(_T('category_nokeyword'));
		$category_enc = urlencode($category); $category_md5 = md5($category);
		$page = isset($_GET['p']) ? @intval($_GET['p']) : 1; if($page < 1) $page = 1; // Current number of pages viewed
		$isrecache = isset($_GET['recache']); // Whether to force the cache to be regenerated

		// Use the session to cache the category tags to appear in the article category to reduce the burden
		if(!isset($_SESSION['loglist_'.$category_md5]) || $isrecache){
			$loglist = $PIO->searchCategory($category);
			$_SESSION['loglist_'.$category_md5] = serialize($loglist);
		}else $loglist = unserialize($_SESSION['loglist_'.$category_md5]);

		$loglist_count = count($loglist);
		$page_max = ceil($loglist_count / $this->config['PAGE_DEF']); if($page > $page_max) $page = $page_max; // Total pages

		// Slice the array and get the range for pagination purposes
		$loglist_cut = array_slice($loglist, $this->config['PAGE_DEF'] * ($page - 1), $this->config['PAGE_DEF']); // Take out a specific range of articles
		$loglist_cut_count = count($loglist_cut);

		$dat = '';
		head($dat);
		$links = '[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">'._T('return').'</a>] [<a href="'.$this->config['PHP_SELF'].'?mode=category&c='.$category_enc.'&recache=1">'._T('category_recache').'</a>]';
		$level = $AccountIO->valid();
		$PMS->useModuleMethods('LinksAboveBar', array(&$links,'category',$level));
		$dat .= "<div>$links</div>\n";
		for($i = 0; $i < $loglist_cut_count; $i++){
			$posts = $PIO->fetchPosts($loglist_cut[$i]); // Get article content
			$dat .= arrangeThread($PTE, ($posts[0]['resto'] ? $posts[0]['resto'] : $posts[0]['no']), null, $posts, 0, $loglist_cut[$i], array(), array(), false, false, false); // Output by output (reference links are not displayed)
		}

		$dat .= '<table id="pager" border="1"><tr>';
		if($page > 1) $dat .= '<td><form action="'.$this->config['PHP_SELF'].'?mode=category&c='.$category_enc.'&p='.($page - 1).'" method="post"><div><input type="submit" value="'._T('prev_page').'"></div></form></td>';
		else $dat .= '<td nowrap="nowrap">'._T('first_page').'</td>';
		$dat .= '<td>';
		for($i = 1; $i <= $page_max ; $i++){
			if($i==$page) $dat .= "[<b>".$i."</b>] ";
			else $dat .= '[<a href="'.$this->config['PHP_SELF'].'?mode=category&c='.$category_enc.'&p='.$i.'">'.$i.'</a>] ';
		}
		$dat .= '</td>';
		if($page < $page_max) $dat .= '<td><form action="'.$this->config['PHP_SELF'].'?mode=category&c='.$category_enc.'&p='.($page + 1).'" method="post"><div><input type="submit" value="'._T('next_page').'"></div></form></td>';
		else $dat .= '<td nowrap="nowrap">'._T('last_page').'</td>';
		$dat .= '</tr></table>';

		foot($dat);
		echo $dat;
	}
}
