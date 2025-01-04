<?php
class mod_cat extends ModuleHelper {
	private $mypage;
	private $PAGE_DEF = 200;
	private $RESICON = -1;
	private $THUMB_EXT = -1;

	public function __construct($PMS) {
		parent::__construct($PMS);
				
		$this->THUMB_EXT = $this->config['THUMB_SETTING']['Format'];
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
			<form action="koko.php?mode=module&load=mod_cat" method="post">
				<span>Sort by:</span>
				<select name="sort_by" style="display: inline-block">
					<option value="bump"'.$bumpSelected.'>Bump order</option>
					<option value="time"'.$timeSelected.'>Creation date</option>
				</select>
				<input type="submit" value="Apply">
			</form>';
	}

	public function ModulePage(){
		$PTE = PTELibrary::getInstance();
		$PMS = PMS::getInstance();
		$PIO = PIOPDO::getInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		
		$globalHTML = new globalHTML($this->board);
		
		$dat = '';

		$list_max = $PIO->threadCountFromBoard($this->board);
		$page = $_GET['page']??0;
		$post_cnt = $PIO->postCountFromBoard($this->board);
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
 
		//sort threads. If sort is set to bump nothing will change because that is the default order returned by fetchThreadList
		switch($sort) {
			case 'time':
				$plist = $PIO->getThreadListFromBoard($this->board, $this->PAGE_DEF * $page, $this->PAGE_DEF, true);
				$sortfcn = function ($a, $b) { return strtotime($b['thread_created_time']) - strtotime($a['thread_created_time']); };
				
			break;
			case 'bump':
			default:
				$plist = $PIO->getThreadListFromBoard($this->board, $this->PAGE_DEF * $page, $this->PAGE_DEF); //thread list
				$sortfcn = function ($a, $b) { return strtotime($b['last_bump_time']) - strtotime($a['last_bump_time']); };
			break;
		}

		$threadList = $PIO->fetchThreads($plist) ?? [];
		usort($threadList, $sortfcn);

		$cat_cols = $_COOKIE['cat_cols']??0;
		$cat_fw = ($_COOKIE['cat_fw']??'false')=='true';
		if (!$cat_cols=intval($cat_cols))
			$cat_cols = 'auto';

		$globalHTML->head($dat);
		$dat.= '
		<script type="text/javascript" src="'.$this->config['STATIC_URL'].'js/catalog.js"></script>
		<div id="catalog">
[<a href="'.$this->config['PHP_SELF2'].'?'.time().'">Return</a>] '.$this->drawSortOptions($sort).'
<center class="theading2"><b>Catalog</b></center>';

		$dat.= '<style>';
		if ($cat_fw) {
			$dat.= '#catalog>table { width: 100%; }';
		}
		if ($cat_cols=='auto') {
			$dat.='
#catalog>table {
	text-align: center;
}
#catalog>table tr, #catalog>table tbody {
	display: inline;
}
#catalog>table td {
	display: inline-block;
	margin: 0.5em;
}';
		}
		$dat.= '</style>';
		$dat.= '<table align="CENTER" cellpadding="0" cellspacing="20"><tbody><tr>';
		foreach($threadList as $i=>$thread){
			$opPost = $PIO->fetchPostsFromThread($thread['thread_uid'])[0];
			extract($opPost);
			
			$resno = $PIO->resolveThreadNumberFromUID($thread_uid);
			if ( ($cat_cols!='auto') && !($i%intval($cat_cols)) )
				$dat.= '</tr><tr>';

			if (!$sub)
				$sub = 'No Title';
			
			$arrLabels = array('{$IMG_BAR}'=>'', '{$POSTINFO_EXTRA}'=>'');
			$PMS->useModuleMethods('ThreadPost', array(&$arrLabels, $posts[$i], false)); // "ThreadPost" Hook Point

			$res = $PIO->getPostCountFromThread($thread_uid) - 1;
			$dat.= '<td class="thread" width="180" height="200" align="CENTER">
	<div class="filesize">'.$arrLabels['{$IMG_BAR}'].'</div>
	<a href="'.$this->config['PHP_SELF'].'?res='.($resno?$resno:$no).'#p'.$no.'">'.
	($FileIO->imageExists($tim.$ext, $this->board) ? '<img src="'.$FileIO->getImageURL($FileIO->resolveThumbName($tim, $this->board), $this->board).'" width="'.min(150, $tw).'" vspace="3"	class="thumb">' : '***').
	'</a><br>
	<nobr><small><b class="title">'.substr($sub, 0, 20).'</b>:'.
		$arrLabels['{$POSTINFO_EXTRA}'].'&nbsp;<span title="Replies"><img src="'.$this->RESICON.'" class="icon"> '.$res.'</small></span></nobr><br>
	<small>'.$com.'</small>
</td>';
		}

		$dat .= '</tr></tbody></table><hr>';

		$dat .= '</div><table id="pager" border="1"><tbody><tr>';
		$pageurl = $this->mypage."&sort_by={$sort}";
		if($page)
			$dat .= '<td nowrap="nowrap"><a href="'.$pageurl.'&page='.($page - 1).'">Previous</a></td>';
		else
			$dat .= '<td nowrap="nowrap">First</td>';
		$dat .= '<td nowrap="nowrap">';
		for($i = 0; $i <= $page_max; $i++){
			if($i==$page)
				$dat .= '[<b>'.$i.'</b>] ';
			else
				$dat .= '[<a href="'.$pageurl.'&page='.$i.'">'.$i.'</a>] ';
		}
		$dat .= '</td>';
		if($page < $page_max)
			$dat .= '<td><a href="'.$pageurl.'&page='.($page + 1).'">Next</a></td>';
		else
			$dat .= '<td nowrap="nowrap">Last</td>';
		$dat .= '</tr></tbody></table><br clear="ALL">';
		$globalHTML->foot($dat);
		echo $dat;
	}

	/* Optimize the display size of the picture */
	private function OptimizeImageWH($w, $h){
		if($w > $this->config['MAX_RW'] || $h > $this->config['MAX_RH']){
			$W2 = $this->config['MAX_RW'] / $w; $H2 = $this->config['MAX_RH'] / $h;
			$tkey = ($W2 < $H2) ? $W2 : $H2;
			$w = ceil($w * $tkey); $h = ceil($h * $tkey);
		}
		return 'width: '.$w.'px; height: '.$h.'px;';
	}
}