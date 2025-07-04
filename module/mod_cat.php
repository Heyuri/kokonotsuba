<?php
class mod_cat extends moduleHelper {
	private $mypage;
	private $PAGE_DEF = 200;
	private $RESICON = -1;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);				
		$this->RESICON = $this->config['STATIC_URL'].'image/replies.png';
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName(){
		return __CLASS__.' : K! Catalog';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function autoHookToplink(&$linkbar, $isreply){
		$linkbar .= ' [<a href="'.$this->mypage.'">Catalog</a>] ';
	}

	private function drawSortOptions($sort = 'bump') {
		$timeSelected = $bumpSelected = '';
		if ($sort == 'bump') {
			$bumpSelected = ' selected';
		} else if ($sort == 'time') {
			$timeSelected = ' selected';
		}
		return '
			<form id="catalogSortForm" action="koko.php?mode=module&load=mod_cat" method="post">
				<span>Sort by:</span>
				<select name="sort_by">
					<option value="bump"'.$bumpSelected.'>Bump order</option>
					<option value="time"'.$timeSelected.'>Creation date</option>
				</select>
				<input type="submit" value="Apply">
			</form>';
	}

	public function ModulePage(){
		$threadSingleton = threadSingleton::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		
		$globalHTML = new globalHTML($this->board);
		
		$dat = '';

		$list_max = $threadSingleton->threadCountFromBoard($this->board);
		$page = $_GET['page']??0;
		$page_max = ceil($list_max / $this->PAGE_DEF) - 1;

		$sort = $_POST['sort_by'] ?? $_GET['sort_by'] ?? $_COOKIE['cat_sort_by'] ?? '';
		if (!in_array($sort, array('bump', 'time'))) {
			$sort = 'bump';
		}

		if($page < 0 || $page > $page_max) {
			$globalHTML->error('Page out of range.');
		}

		if (isset($_POST['sort_by'])) {
			setcookie('cat_sort_by', $sort, time() + 365 * 86400);
		}

		$sortingColumn = 'last_bump_time';
 
		//sort threads. If sort is set to bump nothing will change because that is the default order returned by fetchThreadList
		switch($sort) {
			case 'time':
				$sortingColumn = 'thread_created_time'; 
			break;
			case 'bump':
			default:
				$sortingColumn = 'last_bump_time';
			break;
		}

		$cat_cols = $_COOKIE['cat_cols']??0;
		$cat_fw = ($_COOKIE['cat_fw']??'false')=='true';
		if (!$cat_cols=intval($cat_cols))
			$cat_cols = 'auto';

		$globalHTML->head($dat);
		$dat.= '
		<script src="'.$this->config['STATIC_URL'].'js/catalog.js"></script>
		<div id="catalog">
[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">Return</a>]
<h2 class="theading2">Catalog</h2> '.$this->drawSortOptions($sort).'';
				
		$dat.= '<table id="catalogTable" class="' . ($cat_fw ? 'full-width' : '') . ' ' . ($cat_cols === 'auto' ? 'auto-cols' : 'fixed-cols') . '"><tbody><tr>';

		$threads = $threadSingleton->getThreadsWithAllRepliesFromBoard($this->board, $this->PAGE_DEF, $page * $this->PAGE_DEF, $sortingColumn);
		
		foreach($threads as $i=>$thread){
			$threadPosts = $thread['posts'];
			
			$opPost = $threadPosts[0];

			if(!$opPost) continue;

			extract($opPost);
			
			$resno = $no;
			if ( ($cat_cols!='auto') && !($i%intval($cat_cols)) )
				$dat.= '</tr><tr>';

			if (!$sub)
				$sub = 'No subject';
			
			$arrLabels = array('{$IMG_BAR}'=>'', '{$POSTINFO_EXTRA}'=>'', '{$IMG_SRC}' => '');
			$this->moduleEngine->useModuleMethods('ThreadPost', array(&$arrLabels, $opPost, $threadPosts, false)); // "ThreadPost" Hook Point

			// number of replies (excluding OP)
			$replyCount = count($threadPosts) - 1;
			$dat.= '<td class="thread">
	<!--<div class="filesize">'.$arrLabels['{$IMG_BAR}'].'</div>-->
	<a href="'.$this->board->getBoardThreadURL($resno, $no).'">'.
	($FileIO->imageExists($tim.$ext, $this->board) ? '<img src="'.$FileIO->getImageURL($FileIO->resolveThumbName($tim, $this->board), $this->board).'" width="'.min(150, $tw).'" class="thumb" alt="Thumbnail">' : '***').
	'</a>
	<div class="catPostInfo"><span class="title">'.$sub.'</span>'.
		$arrLabels['{$POSTINFO_EXTRA}'].'&nbsp;<span title="Replies"><img src="'.$this->RESICON.'" class="icon" alt="Replies"> '.$replyCount.'</span></div>
	<div class="catComment">'.$com.'</div>
</td>';
		}

		$dat .= '</tbody></table></div><hr>';
		$dat .= $globalHTML->drawPager($this->PAGE_DEF,$list_max, $this->mypage);
		$globalHTML->foot($dat);
		echo $dat;
	}

}