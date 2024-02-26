<?php
// animated gif module made for kokonotsuba by deadking
class mod_cat extends ModuleHelper {
	private $mypage;
	private $PAGE_DEF = 1000;
	private $RESICON = STATIC_URL.'/image/replies.png';
	private $THUMB_EXT = THUMB_SETTING['Format'];

	public function __construct($PMS) {
		parent::__construct($PMS);
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

	public function ModulePage(){
		$PTE = PMCLibrary::getPTEInstance();
		$PMS = PMCLibrary::getPMSInstance();
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		$dat = '';

		$list_max = $PIO->postCount();
		$page = $_GET['page']??0;
		$plist = $PIO->fetchThreadList($this->PAGE_DEF * $page, $this->PAGE_DEF);
		$post_cnt = count($plist);
		$page_max = ceil($post_cnt / $this->PAGE_DEF) - 1;

		if($page < 0 || $page > $page_max) {
			error('Page out of range.');
		}

		if(THREAD_PAGINATION){ // Catalog caching
			$cacheETag = md5($page.'-'.$post_cnt);
			$cacheFile = STORAGE_PATH.'cache/catalog-'.$page.'.';
			$cacheGzipPrefix = extension_loaded('zlib') ? 'compress.zlib://' : '';
			$cacheControl = 'cache';
			//$cacheControl = isset($_SERVER['HTTP_CACHE_CONTROL']) ? $_SERVER['HTTP_CACHE_CONTROL'] : ''; // respect user's cache wishes? (comment out to force caching)
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == '"'.$cacheETag.'"'){
				header('HTTP/1.1 304 Not Modified');
				header('ETag: "'.$cacheETag.'"');
				return;
			}elseif(file_exists($cacheFile.$cacheETag) && $cacheControl != 'no-cache'){
				header('X-Cache: HIT');
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
				readfile($cacheGzipPrefix.$cacheFile.$cacheETag);
				return;
			}else{
				header('X-Cache: MISS');
			}
		}

		$cat_cols = $_COOKIE['cat_cols']??0;
		$cat_fw = ($_COOKIE['cat_fw']??'false')=='true';
		if (!$cat_cols=intval($cat_cols))
			$cat_cols = 'auto';

		head($dat);
		$dat.= '
		<script type="text/javascript" src="'.$ROOTPATH.'/js/catalog.js"></script>
		<div id="catalog">
[<a href="'.PHP_SELF2.'?'.time().'">Return</a>]
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
		for($i = 0; $i < $post_cnt; $i++){
			$post = $PIO->fetchPosts($plist[$i]);
			extract($post[0]);
			if ( ($cat_cols!='auto') && !($i%intval($cat_cols)) )
				$dat.= '</tr><tr>';

			if (!$sub)
				$sub = 'No Title';
			
			$arrLabels = array('{$IMG_BAR}'=>'', '{$POSTINFO_EXTRA}'=>'');
			$PMS->useModuleMethods('ThreadPost', array(&$arrLabels, $post[0], false)); // "ThreadPost" Hook Point

			$res = count($PIO->fetchPostList($no)) - 1;
			$dat.= '<td class="thread" width="180" height="200" align="CENTER">
	<div class="filesize">'.$arrLabels['{$IMG_BAR}'].'</div>
	<a href="'.PHP_SELF.'?res='.($resto?$resto:$no).'#p'.$no.'">'.
	($FileIO->imageExists($tim.$ext) ? '<img src="'.$FileIO->getImageURL($tim.'s.'.$this->THUMB_EXT).'" width="'.min(150, $tw).'" vspace="3"	class="thumb" />' : '***').
	'</a><br />
	<nobr><small><b class="title">'.substr($sub, 0, 20).'</b>:'.
		$arrLabels['{$POSTINFO_EXTRA}'].'&nbsp;<span title="Replies"><img src="'.$this->RESICON.'" class="icon" /> '.$res.'</small></span></nobr><br />
	<small>'.$com.'</small>
</td>';
		}

		$dat .= '</tr></tbody></table><hr />';

		$dat .= '</div><table id="pager" border="1"><tbody><tr>';
		if($page)
			$dat .= '<td nowrap="nowrap"><a href="'.$this->mypage.'&page='.($page - 1).'">Previous</a></td>';
		else
			$dat .= '<td nowrap="nowrap">First</td>';
		$dat .= '<td nowrap="nowrap">';
		for($i = 0; $i <= $page_max; $i++){
			if($i==$page)
				$dat .= '[<b>'.$i.'</b>] ';
			else
				$dat .= '[<a href="'.$this->mypage.'&page='.$i.'">'.$i.'</a>] ';
		}
		$dat .= '</td>';
		if($page < $page_max)
			$dat .= '<td><a href="'.$this->mypage.'&page='.($page + 1).'">Next</a></td>';
		else
			$dat .= '<td nowrap="nowrap">Last</td>';
		$dat .= '</tr></tbody></table><br clear="ALL" />';
		foot($dat);
		if (THREAD_PAGINATION){ // Catalog caching
			if ($oldCaches = glob($cacheFile.'*')){
				foreach($oldCaches as $o) unlink($o);
			}
			@$fp = fopen($cacheGzipPrefix.$cacheFile.$cacheETag, 'w');
			if($fp){
				fwrite($fp, $dat);
				fclose($fp);
				@chmod($cacheFile.$cacheETag, 0666);
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
			}
		}
		echo $dat;
	}

	/* Optimize the display size of the picture */
	private function OptimizeImageWH($w, $h){
		if($w > MAX_RW || $h > MAX_RH){
			$W2 = MAX_RW / $w; $H2 = MAX_RH / $h;
			$tkey = ($W2 < $H2) ? $W2 : $H2;
			$w = ceil($w * $tkey); $h = ceil($h * $tkey);
		}
		return 'width: '.$w.'px; height: '.$h.'px;';
	}
}
