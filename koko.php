<?php

define("PIXMICAT_VER", 'Koko BBS Release 1'); // 版本資訊文字
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/
if (file_exists('.lockdown')&&!(valid()>=LEV_JANITOR)) {
	die('Posting temporarily disabled. Come back later!');
}

@session_start();

require './config.php'; // 引入設定檔
require ROOTPATH.'lib/pmclibrary.php'; // 引入函式庫
require ROOTPATH.'lib/lib_errorhandler.php'; // 引入全域錯誤捕捉
require ROOTPATH.'lib/lib_compatible.php'; // 引入相容函式庫
require ROOTPATH.'lib/lib_common.php'; // 引入共通函式檔案

/* 更新記錄檔檔案／輸出討論串 */
function updatelog($resno=0,$pagenum=-1,$single_page=false){
	global $LIMIT_SENSOR;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$pagenum = intval($pagenum);

	$adminMode = valid()>=LEV_JANITOR && $pagenum != -1 && !$single_page; // 前端管理模式
	$adminFunc = ''; // 前端管理選擇
	if($adminMode){
		$adminFunc = '<input type="hidden" name="func" value="delete" />';
	}
	$resno = intval($resno); // 編號數字化
	$page_start = $page_end = 0; // 靜態頁面編號
	$inner_for_count = 1; // 內部迴圈執行次數
	$RES_start = $RES_amount = $hiddenReply = $tree_count = 0;
	$kill_sensor = $old_sensor = false; // 預測系統啟動旗標
	$arr_kill = $arr_old = array(); // 過舊編號陣列
	$pte_vals = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'','{$SELF}'=>PHP_SELF,
		'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel" />'._T('del_head'),
		'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" />',
		'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
		'{$DEL_PASS_TEXT}' => ($adminMode ? $adminFunc : '')._T('del_pass'),
		'{$DEL_PASS_FIELD}' => '<input type="password" name="pwd" size="8" value="" />',
		'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'" />',
		'{$IS_THREAD}' => !!$resno);
	if($resno) $pte_vals['{$RESTO}'] = $resno;

	if(!$resno){
		if($pagenum==-1){ // rebuild模式 (PHP動態輸出多頁份)
			$threads = $PIO->fetchThreadList(); // 取得全討論串列表
			$PMS->useModuleMethods('ThreadOrder', array($resno,$pagenum,$single_page,&$threads)); // "ThreadOrder" Hook Point
			$threads_count = count($threads);
			$inner_for_count = $threads_count > PAGE_DEF ? PAGE_DEF : $threads_count;
			$page_end = ceil($threads_count / PAGE_DEF); // 頁面編號最後值
		}else{ // 討論串分頁模式 (PHP動態輸出一頁份)
			$threads_count = $PIO->threadCount(); // 討論串個數
			if($pagenum < 0 || ($pagenum * PAGE_DEF) >= $threads_count) error(_T('page_not_found')); // $pagenum超過範圍
			$page_start = $page_end = $pagenum; // 設定靜態頁面編號
			$threads = $PIO->fetchThreadList(); // 取得全討論串列表
			$PMS->useModuleMethods('ThreadOrder', array($resno,$pagenum,$single_page,&$threads)); // "ThreadOrder" Hook Point
			$threads = array_splice($threads, $pagenum * PAGE_DEF, PAGE_DEF); // 取出分頁後的討論串首篇列表
			$inner_for_count = count($threads); // 討論串個數就是迴圈次數
		}
	}else{
		if(!$PIO->isThread($resno)){ error(_T('thread_not_found')); }
		$AllRes = isset($pagenum) && ($_GET['pagenum']??'')=='all'; // 是否使用 ALL 全部輸出

		// 計算回應分頁範圍
		$tree_count = $PIO->postCount($resno) - 1; // 討論串回應個數
		if($tree_count && RE_PAGE_DEF){ // 有回應且RE_PAGE_DEF > 0才做分頁動作
			if($pagenum==='all'){ // show all
				$pagenum = 0;
				$RES_start = 1; $RES_amount = $tree_count;
			}else{
				if($pagenum==='RE_PAGE_MAX') $pagenum = ceil($tree_count / RE_PAGE_DEF) - 1; // 特殊值：最末頁
				if($pagenum < 0) $pagenum = 0; // 負數
				if($pagenum * RE_PAGE_DEF >= $tree_count) error(_T('page_not_found'));
				$RES_start = $pagenum * RE_PAGE_DEF + 1; // 開始
				$RES_amount = RE_PAGE_DEF; // 取幾個
			}
		}elseif($pagenum > 0) error(_T('page_not_found')); // 沒有回應的情況只允許pagenum = 0 或負數
		else{ $RES_start = 1; $RES_amount = $tree_count; $pagenum = 0; } // 輸出全部回應

		if(THREAD_PAGINATION && !$adminMode){ // Thread Pagination
			$cacheETag = md5(($AllRes ? 'all' : $pagenum).'-'.$tree_count);
			$cacheFile = STORAGE_PATH .'cache/'.$resno.'-'.($AllRes ? 'all' : $pagenum).'.';
			$cacheGzipPrefix = extension_loaded('zlib') ? 'compress.zlib://' : ''; // Zlib compression stream
			$cacheControl = 'cache';
			//$cacheControl = isset($_SERVER['HTTP_CACHE_CONTROL']) ? $_SERVER['HTTP_CACHE_CONTROL'] : ''; // respect user's cache wishes? (comment this line out to force caching)
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == '"'.$cacheETag.'"'){ // Same page
				header('HTTP/1.1 304 Not Modified');
				header('ETag: "'.$cacheETag.'"');
				return;
			}elseif(file_exists($cacheFile.$cacheETag) && $cacheControl != 'no-cache'){ // Send paginated html file
				header('X-Cache: HIT'); // Send buffered request
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
				readfile($cacheGzipPrefix.$cacheFile.$cacheETag);
				return;
			}else{
				header('X-Cache: MISS');
			}
		}
	}

	// 預測過舊文章和將被刪除檔案
	if(PIOSensor::check('predict', $LIMIT_SENSOR)){ // 是否需要預測
		$old_sensor = true; // 標記打開
		$arr_old = array_flip(PIOSensor::listee('predict', $LIMIT_SENSOR)); // 過舊文章陣列
	}
	$tmp_total_size = $FileIO->getCurrentStorageSize(); // 目前附加圖檔使用量
	$tmp_STORAGE_MAX = STORAGE_MAX * (($tmp_total_size >= STORAGE_MAX) ? 1 : 0.95); // 預估上限值
	if(STORAGE_LIMIT && STORAGE_MAX > 0 && ($tmp_total_size >= $tmp_STORAGE_MAX)){
		$kill_sensor = true; // 標記打開
		$arr_kill = $PIO->delOldAttachments($tmp_total_size, $tmp_STORAGE_MAX); // 過舊附檔陣列
	}

	$PMS->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], $resno)); // "ThreadFront" Hook Point
	$PMS->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], $resno)); // "ThreadRear" Hook Point

	// 生成靜態頁面一頁份內容
	for($page = $page_start; $page <= $page_end; $page++){

		$dat = ''; $pte_vals['{$THREADS}'] = '';
		head($dat, $resno);
		// form
		$qu = '';
		if (USE_QUOTESYSTEM && $resno && isset($_GET['q'])) {
			$qq = explode(',', $_GET['q']);
			foreach ($qq as $q) {
				$q = intval($q);
				if ($q<1) continue;
				$qu.= '&gt;&gt;'.intval($q)."\r\n";
			}
		}
		$form_dat = '';
		form($form_dat, $resno, '', '', '', $qu);
		$pte_vals['{$FORMDAT}'] = $form_dat;
		unset($qu);
		// 輸出討論串內容
		for($i = 0; $i < $inner_for_count; $i++){
			// 取出討論串編號
			if($resno) $tID = $resno; // 單討論串輸出 (回應模式)
			else{
				if($pagenum == -1 && ($page * PAGE_DEF + $i) >= $threads_count) break; // rebuild 超出索引代表已全部完成
				$tID = ($page_start==$page_end) ? $threads[$i] : $threads[$page * PAGE_DEF + $i]; // 一頁內容 (一般模式) / 多頁內容 (rebuild模式)
				$tree_count = $PIO->postCount($tID) - 1; // 討論串回應個數
				$RES_start = $tree_count - RE_DEF + 1; if($RES_start < 1) $RES_start = 1; // 開始
				$RES_amount = RE_DEF; // 取幾個
				$hiddenReply = $RES_start - 1; // 被隱藏回應數
			}

			// $RES_start, $RES_amount 拿去算新討論串結構 (分頁後, 部分回應隱藏)
			$tree = $PIO->fetchPostList($tID); // 整個討論串樹狀結構
			$tree_cut = array_slice($tree, $RES_start, $RES_amount); array_unshift($tree_cut, $tID); // 取出特定範圍回應
			$posts = $PIO->fetchPosts($tree_cut); // 取得文章架構內容
			$pte_vals['{$THREADS}'] .= arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno, $arr_kill, $arr_old, $kill_sensor, $old_sensor, true, $adminMode, $inner_for_count); // 交給這個函式去搞討論串印出
		}
		$pte_vals['{$PAGENAV}'] = '';

		// 換頁判斷
		$prev = ($resno ? $pagenum : $page) - 1;
		$next = ($resno ? $pagenum : $page) + 1;
		if($resno){ // 回應分頁
			if(RE_PAGE_DEF > 0){ // 回應分頁開啟
				$pte_vals['{$PAGENAV}'] .= '<table border="1" id="pager"><tbody><tr><td nowrap="nowrap">';
				$pte_vals['{$PAGENAV}'] .= ($prev >= 0) ? '<a rel="prev" href="'.PHP_SELF.'?res='.$resno.'&pagenum='.$prev.'">'._T('prev_page').'</a>' : _T('first_page');
				$pte_vals['{$PAGENAV}'] .= "</td><td>";
				if($tree_count==0) $pte_vals['{$PAGENAV}'] .= '[<b>0</b>] '; // 無回應
				else{
					for($i = 0, $len = $tree_count / RE_PAGE_DEF; $i <= $len; $i++){
						if(!$AllRes && $pagenum==$i) $pte_vals['{$PAGENAV}'] .= '[<b>'.$i.'</b>] ';
						else $pte_vals['{$PAGENAV}'] .= '[<a href="'.PHP_SELF.'?res='.$resno.'&pagenum='.$i.'">'.$i.'</a>] ';
					}
					$pte_vals['{$PAGENAV}'] .= $AllRes ? '[<b>'._T('all_pages').'</b>] ' : ($tree_count > RE_PAGE_DEF ? '[<a href="'.PHP_SELF.'?res='.$resno.'">'._T('all_pages').'</a>] ' : '');
				}
				$pte_vals['{$PAGENAV}'] .= '</td><td nowrap="nowrap">';
				$pte_vals['{$PAGENAV}'] .= (!$AllRes && $tree_count > $next * RE_PAGE_DEF) ? '<a href="'.PHP_SELF.'?res='.$resno.'&pagenum='.$next.'">'._T('next_page').'</a>' : _T('last_page');
				$pte_vals['{$PAGENAV}'] .= '</td></tr></tbody></table>';
			}
		}else{ // 一般分頁
			$pte_vals['{$PAGENAV}'] .= '<table border="1" id="pager"><tbody><tr>';
			if($prev >= 0){
				if(!$adminMode && $prev==0) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.PHP_SELF2.'" method="get">';
				else{
					if($adminMode || (STATIC_HTML_UNTIL != -1) && ($prev > STATIC_HTML_UNTIL)) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.PHP_SELF.'?pagenum='.$prev.'" method="post">';
					else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$prev.PHP_EXT.'" method="get">';
				}
				$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('prev_page').'" /></div></form></td>';
			}else $pte_vals['{$PAGENAV}'] .= '<td nowrap="nowrap">'._T('first_page').'</td>';
			$pte_vals['{$PAGENAV}'] .= '<td>';
			for($i = 0, $len = $threads_count / PAGE_DEF; $i <= $len; $i++){
				if($page==$i) $pte_vals['{$PAGENAV}'] .= "[<b>".$i."</b>] ";
				else{
					$pageNext = ($i==$next) ? ' rel="next"' : '';
					if(!$adminMode && $i==0) $pte_vals['{$PAGENAV}'] .= '[<a href="'.PHP_SELF2.'?">0</a>] ';
					elseif($adminMode || (STATIC_HTML_UNTIL != -1 && $i > STATIC_HTML_UNTIL)) $pte_vals['{$PAGENAV}'] .= '[<a href="'.PHP_SELF.'?pagenum='.$i.'"'.$pageNext.'>'.$i.'</a>] ';
					else $pte_vals['{$PAGENAV}'] .= '[<a href="'.$i.PHP_EXT.'?"'.$pageNext.'>'.$i.'</a>] ';
				}
			}
			$pte_vals['{$PAGENAV}'] .= '</td>';
			if($threads_count > $next * PAGE_DEF){
				if($adminMode || (STATIC_HTML_UNTIL != -1) && ($next > STATIC_HTML_UNTIL)) $pte_vals['{$PAGENAV}'] .= '<td><form action="'.PHP_SELF.'?pagenum='.$next.'" method="post">';
				else $pte_vals['{$PAGENAV}'] .= '<td><form action="'.$next.PHP_EXT.'" method="get">';
				$pte_vals['{$PAGENAV}'] .= '<div><input type="submit" value="'._T('next_page').'" /></div></form></td>';
			}else $pte_vals['{$PAGENAV}'] .= '<td nowrap="nowrap">'._T('last_page').'</td>';
			$pte_vals['{$PAGENAV}'] .= '</tr></tbody></table>';
		}
		$dat .= $PTE->ParseBlock('MAIN', $pte_vals);
		foot($dat,$resno);
		// Remove any preset form values (DO NOT CACHE PRIVATE DETAILS!!!)
		$dat = preg_replace('/id="com" cols="48" rows="4" class="inputtext">(.*)<\/textarea>/','id="com" cols="48" rows="4" class="inputtext"></textarea>',$dat);
		$dat = preg_replace('/name="email" id="email" size="28" value="(.*)" class="inputtext" \/>/','name="email" id="email" size="28" value="" class="inputtext" />',$dat);
		$dat = preg_replace('/replyhl/','',$dat);
		// Minify
		if(MINIFY_HTML){
			$dat = html_minify($dat);
		}
		// 存檔 / 輸出
		if($single_page || ($pagenum == -1 && !$resno)){ // 靜態快取頁面生成
			if(THREAD_PAGINATION){
				if($oldCaches = glob(STORAGE_PATH.'cache/catalog-*')){
					foreach($oldCaches as $o) unlink($o); // Clear old catalog caches
				}
				if($oldCaches = glob(STORAGE_PATH.'cache/api-0.*')){
					foreach($oldCaches as $o) unlink($o); // Clear old API caches
				}
			}
			if($page==0) $logfilename = PHP_SELF2;
			else $logfilename = $page.PHP_EXT;
			$fp = fopen($logfilename, 'w');
			stream_set_write_buffer($fp, 0);
			fwrite($fp, $dat);
			fclose($fp);
			@chmod($logfilename, 0666);
			if(STATIC_HTML_UNTIL != -1 && STATIC_HTML_UNTIL==$page) break; // 頁面數目限制
		}else{ // PHP 輸出 (回應模式/一般動態輸出)
			if(THREAD_PAGINATION && !$adminMode && $resno && !isset($_GET['upseries'])){ // Thread pagination
				if($oldCaches = glob(STORAGE_PATH.'cache/api-'.$resno.'.*')){
					foreach($oldCaches as $o) unlink($o); // Clear old API caches
				}
				if($oldCaches = glob($cacheFile.'*')){
					foreach($oldCaches as $o) unlink($o); // Clear old caches
				}
				@$fp = fopen($cacheGzipPrefix.$cacheFile.$cacheETag, 'w');
				if($fp) { // Write new caches
					fwrite($fp, $dat);
					fclose($fp);
					@chmod($cacheFile.$cacheETag, 0666);
					header('ETag: "'.$cacheETag.'"');
					header('Connection: close');
				}
			}
			echo $dat;
			break;
		}
	}
	
}

/* 輸出討論串架構 */
function arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno=0, $arr_kill, $arr_old, $kill_sensor, $old_sensor, $showquotelink=true, $adminMode=false, $threads_shown=0){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$thdat = ''; // 討論串輸出碼
	$posts_count = count($posts); // 迴圈次數
	if(gettype($tree_cut) == 'array') $tree_cut = array_flip($tree_cut); // array_flip + isset 搜尋法
	if(gettype($tree) == 'array') $tree_clone = array_flip($tree);
	// $i = 0 (首篇), $i = 1～n (回應)
	for($i = 0; $i < $posts_count; $i++){
		$imgsrc = $img_thumb = $imgwh_bar = '';
		$IMG_BAR = $REPLYBTN = $QUOTEBTN = $BACKLINKS = $POSTFORM_EXTRA = $WARN_OLD = $WARN_BEKILL = $WARN_ENDREPLY = $WARN_HIDEPOST = '';
		extract($posts[$i]); // 取出討論串文章內容設定變數

		// 設定欄位值
		if(CLEAR_SAGE) $email = preg_replace('/^sage( *)/i', '', trim($email)); // 清除E-mail中的「sage」關鍵字
		if(ALLOW_NONAME==2){ // 強制砍名
			if($email) $now = "<a href=\"mailto:$email\">$now</a>";
		}else{
			if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		}

		$com = quote_link($com);
		$com = quote_unkfunc($com);
		// 設定附加圖檔顯示
		if ($ext) {
			if(!$fname) $fname = $tim;
			$truncated = (strlen($fname)>40 ? substr($fname,0,40).'(&hellip;)' : $fname);
			if ($fname=='SPOILERS') {
				$truncated=$fname;
			} else {
				$truncated.=$ext;
				$fname.=$ext;
			}

			$imageURL = $FileIO->getImageURL($tim.$ext); // image URL
			$thumbName = $FileIO->resolveThumbName($tim); // thumb Name
			$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.STATIC_URL.'image/nothumb.gif" class="postimg" alt="'.$imgsize.'" hspace="20" vspace="3" border="0" align="left" /></a>'; // 預設顯示圖樣式 (無預覽圖時)
			if($tw && $th){
				if ($thumbName != false){ // 有預覽圖
					$thumbURL = $FileIO->getImageURL($thumbName); // thumb URL
//					$img_thumb = '<small>'._T('img_sample').'</small>';
					$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$thumbURL.'" width="'.$tw.'" height="'.$th.'" class="postimg" alt="'.$imgsize.'" title="Click to show full image" hspace="20" vspace="3" border="0" align="left" /></a>';
				}
				if(SHOW_IMGWH) $imgwh_bar = ', '.$imgw.'x'.$imgh; // 顯示附加圖檔之原檔長寬尺寸
			} else $imgsrc = '';
			$IMG_BAR = _T('img_filename').'<a href="'.$imageURL.'" target="_blank" rel="nofollow" onmouseover="this.textContent=\''.$fname.'\';" onmouseout="this.textContent=\''.$truncated.'\'"> '.$truncated.'</a> <a href="'.$imageURL.'" download="'.$fname.'"><div class="download"></div></a> <small>('.$imgsize.$imgwh_bar.')</small> '.$img_thumb;
		}

        // 設定回應 / 引用連結
        if(USE_QUOTESYSTEM) {
            $qu = $_GET['q']??''; if ($qu) $qu.= ',';
            if($resno){ // 回應模式
                if($showquotelink) $QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree[0]."&q=".htmlspecialchars($qu)."".$no.'#postform" class="qu" title="Quote">'.strval($no).'</a>';
                else $QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree."&q=".htmlspecialchars($qu)."".$no.'#postform" title="Quote">'.strval($no).'</a>';
            }else{
                if(!$i)    $REPLYBTN = '[<a href="'.PHP_SELF.'?res='.$no.'">'._T('reply_btn').'</a>]'; // 首篇
                $QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree[0]."&q=".htmlspecialchars($qu)."".$no.'#postform" title="Quote">'.$no.'</a>';
            }
            unset($qu);

			if (USE_BACKLINK) {
				$blref = $PIO->searchPost(array('((?:&gt;|  ^~)+)(?:No\.)?('.$no.')\b'), 'com', 'REG');
				if ($blcnt=count($blref)) {
					$blref = array_reverse($blref);
					foreach ($blref as $ref) {
						$BACKLINKS.= ' <a href="'.PHP_SELF.'?res='.($ref['resto']?$ref['resto']:$ref['no']).'#p'.$ref['no'].'" class="backlink">&gt;&gt;'.$ref['no'].'</a>';
					}
				}
			}
		} else {
			if($resno&&!$i)		$REPLYBTN = '[<a href="'.PHP_SELF.'?res='.$no.'">'._T('reply_btn').'</a>]';
			$QUOTEBTN = $no;
		}

		if($adminMode){ // 前端管理模式
			$modFunc = '';
			$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$i], $resto)); // "AdminList" Hook Point
			$POSTFORM_EXTRA .= $modFunc;
		}

		// 設定討論串屬性
		if(STORAGE_LIMIT && $kill_sensor) if(isset($arr_kill[$no])) $WARN_BEKILL = '<span class="warning">'._T('warn_sizelimit').'</span><br />'; // 預測刪除過大檔
		if(!$i){ // 首篇 Only
			if($old_sensor) if(isset($arr_old[$no])) $WARN_OLD = '<span class="warning">'._T('warn_oldthread').'</span><br />'; // 快要被刪除的提示
			$flgh = $PIO->getPostStatus($status);
			if($hiddenReply) $WARN_HIDEPOST = '<span class="omittedposts">'._T('notice_omitted',$hiddenReply).'</span><br />'; // 有隱藏的回應
		}
		// 對類別標籤作自動連結
		if(USE_CATEGORY){
			$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
			$ary_category_count = count($ary_category);
			$ary_category2 = array();
			for($p = 0; $p < $ary_category_count; $p++){
				if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&c='.urlencode($c).'">'.$c.'</a>';
			}
			$category = implode(', ', $ary_category2);
		}else $category = '';

		$THREADNAV = '';
		$THREADNAV.= '<a href="#postform">&#9632;</a>&nbsp;';
		$THREADNAV.= '<a href="#top">&#9650;</a>&nbsp;';
		$THREADNAV.= '<a href="#bottom">&#9660;</a>&nbsp;';

		// 最終輸出處
		if($i){ // 回應
			$arrLabels = array('{$NO}'=>$no, '{$RESTO}'=>$resto, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$CATEGORY}'=>$category, '{$QUOTEBTN}'=>$QUOTEBTN, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_BEKILL}'=>$WARN_BEKILL, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'), '{$SELF}'=>PHP_SELF, '{$COM}'=>$com, '{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA, '{$THREADNAV}'=>$THREADNAV, '{$BACKLINKS}'=>$BACKLINKS, '{$IS_THREAD}'=>!!$resno);
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadReply', array(&$arrLabels, $posts[$i], $resno)); // "ThreadReply" Hook Point
			$thdat .= $PTE->ParseBlock('REPLY',$arrLabels);
		}else{ // 首篇
			$arrLabels = array('{$NO}'=>$no, '{$RESTO}'=>$no, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$CATEGORY}'=>$category, '{$QUOTEBTN}'=>$QUOTEBTN, '{$REPLYBTN}'=>$REPLYBTN, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_OLD}'=>$WARN_OLD, '{$WARN_BEKILL}'=>$WARN_BEKILL, '{$WARN_ENDREPLY}'=>$WARN_ENDREPLY, '{$WARN_HIDEPOST}'=>$WARN_HIDEPOST, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'), '{$SELF}'=>PHP_SELF, '{$COM}'=>$com, '{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA, '{$THREADNAV}'=>$THREADNAV, '{$BACKLINKS}'=>$BACKLINKS, '{$IS_THREAD}'=>!!$resno);
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadPost', array(&$arrLabels, $posts[$i], $resno)); // "ThreadPost" Hook Point
			$thdat .= $PTE->ParseBlock('THREAD',$arrLabels);
		}
	}
	$thdat .= $PTE->ParseBlock('THREADSEPARATE',($resno)?array('{$RESTO}'=>$resno):array());
	return $thdat;
}

/* post preview */
function previewPost($tmpno) {
	$PIO = PMCLibrary::getPIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	extract($PIO->fetchPosts($tmpno)[0]);
	if(USE_CATEGORY){
		$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
		$ary_category_count = count($ary_category);
		$ary_category2 = array();
		for($p = 0; $p < $ary_category_count; $p++){
			if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&c='.urlencode($c).'">'.$c.'</a>';
		}
		$category = implode(', ', $ary_category2);
	}else $category = '';
	$com = quote_link($com);
	$com = quote_unkfunc($com);

	$dat = '';
	head($dat);
	if (!$resto) $dat.= '[<a href="'.PHP_SELF2.'">Return</a>]';
	form($dat, $resto, $_POST['name'], $_POST['email'], $_POST['sub'], $_POST['com'], $_POST['category'], true);
	$dat.= '[<a href="'.$_SERVER['HTTP_REFERER'].'" onclick="event.preventDefault();history.go(-1);">Back</a>]';
	if(!TEXTBOARD_ONLY && $_FILES['upfile']['error']!=UPLOAD_ERR_NO_FILE) $dat.= ' <span class="warning">Files are not previewed.</span>';
	$arrLabels = array('{$NO}'=>$no, '{$RESTO}'=>$resto,
		'{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$CATEGORY}'=>$category, '{$COM}'=>$com,
		'{$QUOTEBTN}'=>"<a href=\"#postform\">$no</a>", '{$SELF}'=>PHP_SELF,
		'{$IMG_BAR}'=>'', '{$IMG_SRC}'=>'', '{$REPLYBTN}'=>'', '{$IS_PREVIEW}'=>true,
		'{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'), '{$BACKLINKS}'=>'',
		'{$WARN_OLD}'=>'', '{$WARN_BEKILL}'=>'', '{$WARN_ENDREPLY}'=>'', '{$WARN_HIDEPOST}'=>'', '{$POSTINFO_EXTRA}'=>'');
	$dat.= $PTE->ParseBlock($resto?'REPLY':'THREAD',$arrLabels);
	$dat.= $PTE->ParseBlock('THREADSEPARATE',array());
	foot($dat,!!$resto);
	echo $dat;
}

/* 寫入記錄檔 */
function regist($preview=false){
	global $BAD_STRING, $BAD_FILEMD5, $BAD_IPADDR, $LIMIT_SENSOR, $THUMB_SETTING;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$fname = $dest = $mes = ''; $up_incomplete = 0; $is_admin = false;
	$delta_totalsize = 0; // 總檔案大小的更動值

	if(!$_SERVER['HTTP_REFERER']
	|| !$_SERVER['HTTP_USER_AGENT']
	|| preg_match("/^(curl|wget)/i", $_SERVER['HTTP_USER_AGENT']) ){
		error('You look like a robot.', $dest);
	}

	if($_SERVER['REQUEST_METHOD'] != 'POST') error(_T('regist_notpost')); // 非正規POST方式

	$name = CleanStr($_POST['name']??'');
	$email = CleanStr($_POST['email']??'');
	$sub = CleanStr($_POST['sub']??'');
	$com = $_POST['com']??'';
	$pwd = $_POST['pwd']??'';
	$category = CleanStr($_POST['category']??'');
	$resto = intval($_POST['resto']??0);
	$pwdc = $_COOKIE['pwdc']??'';
	$ip = getREMOTE_ADDR(); 
	//$host = gethostbyaddr($ip);
	$host = $ip; //This should improve reliability by a longshot

	$PMS->useModuleMethods('RegistBegin', array(&$name, &$email, &$sub, &$com, array('file'=>&$upfile, 'path'=>&$upfile_path, 'name'=>&$upfile_name, 'status'=>&$upfile_status), array('ip'=>$ip, 'host'=>$host), $resto)); // "RegistBegin" Hook Point
	// 封鎖：IP/Hostname/DNSBL 檢查機能
	$baninfo = '';
	if(BanIPHostDNSBLCheck($ip, $host, $baninfo)) error(_T('regist_ipfiltered', $baninfo));
	// 封鎖：限制出現之文字
	foreach($BAD_STRING as $value){
		if(strpos($com, $value)!==false || strpos($sub, $value)!==false || strpos($name, $value)!==false || strpos($email, $value)!==false){
			error(_T('regist_wordfiltered'));
		}
	}

	// 檢查是否輸入櫻花日文假名
	foreach(array($name, $email, $sub, $com) as $anti) if(anti_sakura($anti)) error(_T('regist_sakuradetected'));

	// 時間
	$time = $_SERVER['REQUEST_TIME'];
	$tim = $time.substr($_SERVER['REQUEST_TIME_FLOAT'],2,3);

	if(!TEXTBOARD_ONLY) {
		$upfile = CleanStr($_FILES['upfile']['tmp_name']??'');
		$upfile_path = $_POST['upfile_path']??'';
		$upfile_name = $_FILES['upfile']['name']??'';
		$upfile_status = $_FILES['upfile']['error']??UPLOAD_ERR_NO_FILE;

		// 判斷上傳狀態
		switch($upfile_status){
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_FORM_SIZE:
				error('ERROR: The file is too large.(upfile)');
				break;
			case UPLOAD_ERR_INI_SIZE:
				error('ERROR: The file is too large.(php.ini)');
				break;
			case UPLOAD_ERR_PARTIAL:
				error('ERROR: The uploaded file was only partially uploaded.');
				break;
			case UPLOAD_ERR_NO_FILE:
				if(!$resto && !isset($_POST['noimg']) && !$preview) error(_T('regist_upload_noimg'));
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				error('ERROR: Missing a temporary folder.');
				break;
			case UPLOAD_ERR_CANT_WRITE:
				error('ERROR: Failed to write file to disk.');
				break;
			default:
				error('ERROR: Unable to save the uploaded file.');
		}

		// 如果有上傳檔案則處理附加圖檔
		if($upfile && (@is_uploaded_file($upfile) || @is_file($upfile)) && !$preview){
			// 一‧先儲存檔案
			$dest = STORAGE_PATH .$tim.'.tmp';
			@move_uploaded_file($upfile, $dest) or @copy($upfile, $dest);
			@chmod($dest, 0666);
			if(!is_file($dest)) error(_T('regist_upload_filenotfound'), $dest);

			// 二‧判斷上傳附加圖檔途中是否有中斷
			$upsizeTTL = $_SERVER['CONTENT_LENGTH'];
			if(isset($_FILES['upfile'])){ // 有傳輸資料才需要計算，避免作白工
				$upsizeHDR = 0;
				// 檔案路徑：IE附完整路徑，故得從隱藏表單取得
				$tmp_upfile_path = $upfile_name;
				if($upfile_path) $tmp_upfile_path = get_magic_quotes_gpc() ? stripslashes($upfile_path) : $upfile_path;
				list(,$boundary) = explode('=', $_SERVER['CONTENT_TYPE']);
				foreach($_POST as $header => $value){ // 表單欄位傳送資料
					$upsizeHDR += strlen('--'.$boundary."\r\n");
					$upsizeHDR += strlen('Content-Disposition: form-data; name="'.$header.'"'."\r\n\r\n".(get_magic_quotes_gpc()?stripslashes($value):$value)."\r\n");
				}
				// 附加圖檔欄位傳送資料
				$upsizeHDR += strlen('--'.$boundary."\r\n");
				$upsizeHDR += strlen('Content-Disposition: form-data; name="upfile"; filename="'.$tmp_upfile_path."\"\r\n".'Content-Type: '.$_FILES['upfile']['type']."\r\n\r\n");
				$upsizeHDR += strlen("\r\n--".$boundary."--\r\n");
				$upsizeHDR += $_FILES['upfile']['size']; // 傳送附加圖檔資料量
				// 上傳位元組差值超過 HTTP_UPLOAD_DIFF：上傳附加圖檔不完全
				if(($upsizeTTL - $upsizeHDR) > HTTP_UPLOAD_DIFF){
					if(KILL_INCOMPLETE_UPLOAD){
						unlink($dest);
						die(_T('regist_upload_killincomp')); // 給瀏覽器的提示，假如使用者還看的到的話才不會納悶
					}else $up_incomplete = 1;
				}
			}

			// 三‧檢查是否為可接受的檔案
			$size = @getimagesize($dest);
			$imgsize = @filesize($dest); // 檔案大小
			$imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // KB和B的判別
			$fname = pathinfo($upfile_name, PATHINFO_FILENAME);
			$ext = '.'.strtolower(pathinfo($upfile_name, PATHINFO_EXTENSION));
			if (is_array($size)) {
				// File extension detection from Heyuri
				// Don't assume the script supports the file type just because the extension is here.
				switch($size[2]){ // 判斷上傳附加圖檔之格式
					case IMAGETYPE_GIF: $ext = '.gif'; break;
					case IMAGETYPE_JPEG:
					case IMAGETYPE_JPEG2000: $ext = '.jpg'; break;
					case IMAGETYPE_PNG: $ext = '.png'; break;
					case IMAGETYPE_SWF:
					case IMAGETYPE_SWC: $ext = '.swf';
						if(!($size[0]&&$size[1])){
							$size[0]=MAX_W;
							$size[1]=MAX_H;
						}
						break;
					case IMAGETYPE_PSD: $ext = '.psd'; break;
					case IMAGETYPE_BMP: $ext = '.bmp'; break;
					case IMAGETYPE_WBMP: $ext = '.wbmp'; break;
					case IMAGETYPE_XBM: $ext = '.xbm'; break;
					case IMAGETYPE_TIFF_II:
					case IMAGETYPE_TIFF_MM:
					case IMAGETYPE_IFF: $ext = '.tiff'; break;
					case IMAGETYPE_JB2: $ext = '.jb2'; break;
					case IMAGETYPE_JPC: $ext = '.jpc'; break;
					case IMAGETYPE_JP2: $ext = '.jp2'; break;
					case IMAGETYPE_JPX: $ext = '.jpx'; break;
					case IMAGETYPE_ICO: $ext = '.ico'; break;
					case IMAGETYPE_WEBP: $ext = '.webp'; break;
				}
			} else {
				$size = array(0, 0, 0);
				$video_exts = explode('|', strtolower(VIDEO_EXT));
				if(array_search(substr($ext, 1), $video_exts)!==false) {
					// Video thumbs
					$tmpfile = tempnam(sys_get_temp_dir(), "thumbnail_");
					rename($tmpfile, $tmpfile.".jpg");
					$tmpfile .= ".jpg";
					@exec("ffmpeg -y -i ".$dest." -ss 00:00:1 -vframes 1 ".$tmpfile." 2>&1");
					$size = @getimagesize($tmpfile);
					$imgsize = @filesize($dest); // 檔案大小
					$imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // KB和B的判別
				}
			}
			$allow_exts = explode('|', strtolower(ALLOW_UPLOAD_EXT)); // 接受之附加圖檔副檔名
			if(array_search(substr($ext, 1), $allow_exts)===false) error(_T('regist_upload_notsupport'), $dest); // 並無在接受副檔名之列
			// 封鎖設定：限制上傳附加圖檔之MD5檢查碼
			$md5chksum = md5_file($dest); // 檔案MD5
			if(array_search($md5chksum, $BAD_FILEMD5)!==false) error(_T('regist_upload_blocked'), $dest); // 在封鎖設定內則阻擋

			// 四‧計算附加圖檔圖檔縮圖顯示尺寸
			$W = $imgW = $size[0];
			$H = $imgH = $size[1];
			$MAXW = $resto ? MAX_RW : MAX_W;
			$MAXH = $resto ? MAX_RH : MAX_H;
			if($W > $MAXW || $H > $MAXH){
				$W2 = $MAXW / $W;
				$H2 = $MAXH / $H;
				$key = ($W2 < $H2) ? $W2 : $H2;
				$W = ceil($W * $key);
				$H = ceil($H * $key);
			}
			if ($ext=='.swf') $W = $H = 0; // dumb flash file thinks it's an image lol.
			$mes = _T('regist_uploaded', CleanStr($upfile_name));
		}
	} else {
		$upfile = '';
		$upfile_path = '';
		$upfile_name = '';
		$upfile_status = 4;
	}

	// 檢查表單欄位內容並修整
	if(strlenUnicode($name) > INPUT_MAX) error(_T('regist_nametoolong'), $dest);
	if(strlenUnicode($email) > INPUT_MAX) error(_T('regist_emailtoolong'), $dest);
	if(strlenUnicode($sub) > INPUT_MAX) error(_T('regist_topictoolong'), $dest);
	if(strlenUnicode($resto) > INPUT_MAX) error(_T('regist_longthreadnum'), $dest);

	setrawcookie('namec', rawurlencode($name), time()+7*24*3600);
	// E-mail / 標題修整
	$email = str_replace("\r\n", '', $email); $sub = str_replace("\r\n", '', $sub);
	// Tripcode crap
	$name = str_replace('&#', '&&', $name); // otherwise HTML numeric entities will explode!
	list($name, $trip, $sectrip) = str_replace('&%', '&#', explode('#',$name.'##'));
	if ($trip) {
		$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($trip.'H.',1,2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
		$trip = '!'.substr(crypt($trip, $salt), -10);
	}
	if ($sectrip) {
		if ($level=valid($sectrip)) {
			// Moderator capcode
			switch ($level) {
				case 1: if (JCAPCODE_FMT) $name = sprintf(JCAPCODE_FMT, $name); break;
				case 2: if (MCAPCODE_FMT) $name = sprintf(MCAPCODE_FMT, $name); break;
				case 3: if (ACAPCODE_FMT) $name = sprintf(ACAPCODE_FMT, $name); break;
			}
		} else {
			// User
			$sha =str_rot13(base64_encode(pack("H*",sha1($sectrip.TRIPSALT))));
			$sha = substr($sha,0,10);
			$trip = '!!'.$sha;
		}
	}
	if(!$name || preg_match("/^[ |　|]*$/", $name)){
		if(ALLOW_NONAME) $name = DEFAULT_NONAME;
		else error(_T('regist_withoutname'), $dest);
	}
	$name = "<b>$name</b>$trip";
	if (isset(CAPCODES[$trip])) {
		$capcode = CAPCODES[$trip];
		$name = '<font color="'.$capcode['color'].'">'.$name.'<b>'.$capcode['cap'].'</b>'.'</font>';
	}
	
	if ($email == "vipcode" && defined('VIPDEF')) {
			$name .= ' <img src="'.STATIC_URL.'vip.gif" title="This user is a VIP user" style="vertical-align: middle;margin-top: -2px;" alt="VIP">'; 
	}
	$email = preg_replace('/^vipcode$/i', '', $email);
	
	// 內文修整
	if((strlenUnicode($com) > COMM_MAX) && !$is_admin) error(_T('regist_commenttoolong'), $dest);
	$com = CleanStr($com, $is_admin); // 引入$is_admin參數是因為當管理員キャップ啟動時，允許管理員依config設定是否使用HTML
	if(!$com && $upfile_status==4) error(TEXTBOARD_ONLY?'ERROR: No text entered.':_T('regist_withoutcomment'));
	$com = str_replace(array("\r\n", "\r"), "\n", $com); $com = preg_replace("/\n((　| )*\n){3,}/", "\n", $com);
	if(!BR_CHECK || substr_count($com,"\n") < BR_CHECK) $com = nl2br($com); // 換行字元用<br />代替
	$com = str_replace("\n", '', $com); // 若還有\n換行字元則取消換行
	if(AUTO_LINK) $com = auto_link($com);
	if (FORTUNES && stristr($email, 'fortune')) {
		if (!$preview) {
			$fortunenum=array_rand(FORTUNES);
			$fortcol=sprintf("%02x%02x%02x",
				127+127*sin(2*M_PI*$fortunenum/count(FORTUNES)),
				127+127*sin(2*M_PI*$fortunenum/count(FORTUNES)+2/3*M_PI),
				127+127*sin(2*M_PI*$fortunenum/count(FORTUNES)+4/3*M_PI));
			$com = "<font color=\"#$fortcol\"><b>Your fortune: ".FORTUNES[$fortunenum]."</b></font><br/><br/>$com";
		} else {
			$com = "<font color=\"#F00\"><b>DON'T TRY TO CHEAT THE SYSTEM!</b></font><br /><br />$com";
		}
	}
		if (ROLL && stristr($email, 'roll')) {
		if (!$preview) {
			$rollnum=array_rand(ROLL);
			$fortcol=sprintf("%02x%02x%02x",
				127+127*sin(2*M_PI*$rollnum/count(ROLL)),
				127+127*sin(2*M_PI*$rollnum/count(ROLL)+2/3*M_PI),
				127+127*sin(2*M_PI*$rollnum/count(ROLL)+4/3*M_PI));
			$com = "<font color='#ff0000'><b>[NUMBER: ".rand(1,10000)."]</b></font><br/><br/>$com";
								$email = preg_replace('/^roll( *)/i', '');
		} else {
			$com = "<font color=\"#F00\"><b>DON'T TRY TO CHEAT THE SYSTEM!</b></font><br /><br />$com";
		}

	}
	// 預設的內容
	if(!$sub || preg_match("/^[ |　|]*$/", $sub)) $sub = DEFAULT_NOTITLE;
	if(!$com || preg_match("/^[ |　|\t]*$/", $com)) $com = DEFAULT_NOCOMMENT;
	// 修整標籤樣式
	if($category && USE_CATEGORY){
		$category = explode(',', $category); // 把標籤拆成陣列
		$category = ','.implode(',', array_map('trim', $category)).','; // 去空白再合併為單一字串 (左右含,便可以直接以,XX,形式搜尋)
	}else{ $category = ''; }
	if($up_incomplete) $com .= '<br /><br /><span class="warning">'._T('notice_incompletefile').'</span>'; // 上傳附加圖檔不完全的提示

	// 密碼和時間的樣式
	if($pwd=='') $pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
	$pass = $pwd ? substr(md5($pwd), 2, 8) : '*'; // 生成真正儲存判斷用的密碼
	$youbi = array(_T('sun'),_T('mon'),_T('tue'),_T('wed'),_T('thu'),_T('fri'),_T('sat'));
	$yd = $youbi[gmdate('w', $time+TIME_ZONE*60*60)];
	$now = gmdate('Y/m/d', $time+TIME_ZONE*60*60).'('.(string)$yd.')'.gmdate('H:i', $time+TIME_ZONE*60*60);
    if(DISP_ID){ //       ID
        if(valid() == LEV_ADMIN and DISP_ID == 2) $now .= ' ID:ADMIN'; else
		if(valid() == LEV_MODERATOR and DISP_ID == 2) $now .= ' ID:MODERATOR'; else
		if(stristr($email, 'sage') and DISP_ID == 2) $now .= ' ID:Heaven';
        else {
            switch (ID_MODE) {
                case 0:					
                    $now .= ' ID:'.substr(crypt(md5(getREMOTE_ADDR().IDSEED.($resto?$resto:($PIO->getLastPostNo("beforeCommit")+1))),'id'), -8);
                    break;
                case 1:
                    $now .= ' ID:'.substr(crypt(md5(getREMOTE_ADDR().IDSEED.($resto?$resto:($PIO->getLastPostNo("beforeCommit")+1)).gmdate('Ymd', $time+TIME_ZONE*60*60)),'id'), -8);
                    break;
            }
        }
    }

	// 連續投稿 / 相同附加圖檔檢查
	$checkcount = 50; // 預設檢查50筆資料
	$pwdc = substr(md5($pwdc), 2, 8); // Cookies密碼
	if (valid()>=LEV_MODERATOR) {
		if($PIO->isSuccessivePost($checkcount, $com, $time, $pass, $pwdc, $host, $upfile_name))
			error(_T('regist_successivepost'), $dest); // 連續投稿檢查
		if($dest){ if($PIO->isDuplicateAttachment($checkcount, $md5chksum)) error(_T('regist_duplicatefile'), $dest); } // 相同附加圖檔檢查
	}
	if($resto) $ThreadExistsBefore = $PIO->isThread($resto);

	// 舊文章刪除處理
	if(PIOSensor::check('delete', $LIMIT_SENSOR)){
		$delarr = PIOSensor::listee('delete', $LIMIT_SENSOR);
		if(count($delarr)){
			deleteCache($delarr);
			$PMS->useModuleMethods('PostOnDeletion', array($delarr, 'recycle')); // "PostOnDeletion" Hook Point
			$files = $PIO->removePosts($delarr);
			if(count($files)) $delta_totalsize -= $FileIO->deleteImage($files); // 更新 delta 值
		}
	}

	// 附加圖檔容量限制功能啟動：刪除過大檔
	if(STORAGE_LIMIT && STORAGE_MAX > 0){
		$tmp_total_size = $FileIO->getCurrentStorageSize(); // 取得目前附加圖檔使用量
		if($tmp_total_size > STORAGE_MAX){
			$files = $PIO->delOldAttachments($tmp_total_size, STORAGE_MAX, false);
			$delta_totalsize -= $FileIO->deleteImage($files);
		}
	}

	// 判斷欲回應的文章是不是剛剛被刪掉了
	if($resto){
		if($ThreadExistsBefore){ // 欲回應的討論串是否存在
			if(!$PIO->isThread($resto)){ // 被回應的討論串存在但已被刪
				// 提前更新資料來源，此筆新增亦不紀錄
				$PIO->dbCommit();
				updatelog();
				error(_T('regist_threaddeleted'), $dest);
			}else{ // 檢查是否討論串被設為禁止回應 (順便取出原討論串的貼文時間)
				$post = $PIO->fetchPosts($resto); // [特殊] 取單篇文章內容，但是回傳的$post同樣靠[$i]切換文章！
				list($chkstatus, $chktime) = array($post[0]['status'], $post[0]['tim']);
				$chktime = substr($chktime, 0, -3); // 拿掉微秒 (後面三個字元)
				$flgh = $PIO->getPostStatus($chkstatus);
			}
		}else error(_T('thread_not_found'), $dest); // 不存在
	}

	// 計算某些欄位值
	$no = $PIO->getLastPostNo('beforeCommit') + 1;
	isset($ext) ? 0 : $ext = '';
	isset($imgW) ? 0 : $imgW = 0;
	isset($imgH) ? 0 : $imgH = 0;
	isset($imgsize) ? 0 : $imgsize = '';
	isset($W) ? 0 : $W = 0;
	isset($H) ? 0 : $H = 0;
	isset($md5chksum) ? 0 : $md5chksum = '';
	$age = false;
	$status = '';
	if ($resto) {
		if ($PIO->postCount($resto) <= MAX_RES || MAX_RES==0) {
			if(!MAX_AGE_TIME || (($time - $chktime) < (MAX_AGE_TIME * 60 * 60))) $age = true; // 討論串並無過期，推文
		}
		if (NOTICE_SAGE && stristr($email, 'sage')) {
			$age = false;
			if (!CLEAR_SAGE) $name.= '&nbsp;<b><font color="#F00">SAGE!</font></b>';
		}
	}

	// noko
	$redirect = PHP_SELF2.'?'.$tim;
	if (strstr($email, 'noko') && !strstr($email, 'nonoko')) {
		$redirect = PHP_SELF.'?res='.($resto?$resto:$no);
		if (!strstr($email, 'noko2')) $redirect.= "#p$no";
	}
	$email = preg_replace('/^(no)+ko\d*$/i', '', $email);

	$PMS->useModuleMethods('RegistBeforeCommit', array(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $resto, array($W, $H, $imgW, $imgH, $tim, $ext), &$status)); // "RegistBeforeCommit" Hook Point
	$PIO->addPost($no,$resto,$md5chksum,$category,$tim,$fname,$ext,$imgW,$imgH,$imgsize,$W,$H,$pass,$now,$name,$email,$sub,$com,$host,$age,$status);
	if($preview) {
		previewPost($no);
		return;
	}

	logtime("Post No.$no registered", valid());
	// 正式寫入儲存
	$PIO->dbCommit();
	$lastno = $PIO->getLastPostNo('afterCommit'); // 取得此新文章編號
	$PMS->useModuleMethods('RegistAfterCommit', array($lastno, $resto, $name, $email, $sub, $com)); // "RegistAfterCommit" Hook Point

	// Cookies儲存：密碼與E-mail部分，期限是一週
	setcookie('pwdc', $pwd, time()+7*24*3600);
	setcookie('emailc', $email, time()+7*24*3600);
	if($dest && is_file($dest)){
		$destFile = IMG_DIR.$tim.$ext; // 圖檔儲存位置
		$thumbFile = THUMB_DIR.$tim.'s.'.$THUMB_SETTING['Format']; // 預覽圖儲存位置
		if (defined(CDN_DIR)) {
			$destFile = CDN_DIR.$destFile;
			$thumbFile = CDN_DIR.$thumbFile;
		}
		if(USE_THUMB !== 0){ // 生成預覽圖
			$thumbType = USE_THUMB; if(USE_THUMB==1){ $thumbType = $THUMB_SETTING['Method']; }
			require(ROOTPATH.'lib/thumb/thumb.'.$thumbType.'.php');
			if (isset($tmpfile)) $thObj = new ThumbWrapper($tmpfile, $imgW, $imgH);
			else $thObj = new ThumbWrapper($dest, $imgW, $imgH);
			$thObj->setThumbnailConfig($W, $H, $THUMB_SETTING);
			$thObj->makeThumbnailtoFile($thumbFile);
			@chmod($thumbFile, 0666);
			unset($thObj);
		}
		rename($dest, $destFile);
		if(file_exists($destFile)){
			$FileIO->uploadImage($tim.$ext, $destFile, filesize($destFile));
			$delta_totalsize += filesize($destFile);
		}
		if(file_exists($thumbFile)){
			$FileIO->uploadImage($tim.'s.'.$THUMB_SETTING['Format'], $thumbFile, filesize($thumbFile));
			$delta_totalsize += filesize($thumbFile);
		}
	}

// webhooks
	if(defined('IRC_WH')){
		$url = 'https:'.fullURL().PHP_SELF."?res=".($resto?$resto:$no)."#p$no";
		$stream = stream_context_create([
			'ssl' =>[
				'verify_peer'=>false,
				'verify_peer_name'=>false,
			],
			'http'=>[
				'method'=>'POST',
				'header'=>'content-type:application/x-www-form-urlencoded',
				'content'=>'content='.htmlspecialchars_decode(($resto?'New post':'New thread')." <$url>", ENT_QUOTES),
			]
		]);
		@file_get_contents(IRC_WH, false, $stream);
	}
	if(defined('DISCORD_WH')){
		$url = 'https:'.fullURL().PHP_SELF."?res=".($resto?$resto:$no)."#p$no";
		$stream = stream_context_create([
			'http'=>[
				'method'=>'POST',
				'header'=>'content-type:application/x-www-form-urlencoded',
				'content'=>http_build_query([
					'content'=>($resto?'New post':'New thread')." <$url>",
				]),
			]
		]);
		@file_get_contents(DISCORD_WH, false, $stream);
	}

			// webhooks with titles
	if(defined('IRC_WH_NEWS') && !$resto){
		$url = 'https:'.fullURL().PHP_SELF."?res=".($resto?$resto:$no);
		$stream = stream_context_create([
						'ssl' =>[
				'verify_peer'=>false,
				'verify_peer_name'=>false,
			],
			'http'=>[
				'method'=>'POST',
				'header'=>'content-type:application/x-www-form-urlencoded',
				'content'=>'content='.htmlspecialchars_decode(($resto?'New post':''." '$sub'")." <$url>", ENT_QUOTES),
			]
		]);
		@file_get_contents(IRC_WH_NEWS, false, $stream);
	}
	
	if(defined('DISCORD_WH_NEWS') && !$resto){
		$url = 'https:'.fullURL().PHP_SELF."?res=".($resto?$resto:$no);
		$stream = stream_context_create([
			'http'=>[
				'method'=>'POST',
				'header'=>'content-type:application/x-www-form-urlencoded',
				'content'=>http_build_query([
					'content'=>($resto?'New post':''." '$sub'")." <$url>",
				]),
			]
		]);
		@file_get_contents(DISCORD_WH_NEWS, false, $stream);
	}


	// delta != 0 表示總檔案大小有更動，須更新快取
	if($delta_totalsize != 0){
		$FileIO->updateStorageSize($delta_totalsize);
	}
	updatelog();

	if(isset($_POST['up_series'])){
		if($resto) $redirect = PHP_SELF.'?res='.$resto.'&upseries=1';
		else $redirect = PHP_SELF.'?res='.$lastno.'&upseries=1';
	}

	redirect($redirect, 0);
}

/* 使用者刪除 */
function usrdel(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	// $pwd: 使用者輸入值, $pwdc: Cookie記錄密碼
	$pwd = $_POST['pwd']??'';
	$pwdc = $_COOKIE['pwdc']??'';
	$onlyimgdel = $_POST['onlyimgdel']??'';
	$delno = array();
	reset($_POST);
	while ($item = each($_POST)){
		if ($item[1] !== 'delete') {
			continue;
		}
		if (!is_numeric($item[0])) {
			continue;
		}
		array_push($delno, intval($item[0]));
	}
	$haveperm = valid()>=LEV_JANITOR;
	$PMS->useModuleMethods('Authenticate', array($pwd,'userdel',&$haveperm));
	if($haveperm && isset($_POST['func'])){ // 前端管理功能
		$message = '';
		$PMS->useModuleMethods('AdminFunction', array('run', &$delno, $_POST['func'], &$message)); // "AdminFunction" Hook Point
		if($_POST['func'] != 'delete'){
			if(isset($_SERVER['HTTP_REFERER'])){
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: '.$_SERVER['HTTP_REFERER']);
			}
			exit(); // 僅執行AdminFunction，終止刪除動作
		}
	}

	if($pwd=='' && $pwdc!='') $pwd = $pwdc;
	$pwd_md5 = substr(md5($pwd),2,8);
	$host = gethostbyaddr(getREMOTE_ADDR());
	$search_flag = $delflag = false;

	if(!count($delno)) error(_T('del_notchecked'));

	$delposts = array(); // 真正符合刪除條件文章
	$posts = $PIO->fetchPosts($delno);
	foreach($posts as $post){
		if($pwd_md5==$post['pwd'] || $host==$post['host'] || $haveperm){
			$search_flag = true; // 有搜尋到
			array_push($delposts, intval($post['no']));
			logtime("Delete post No.".$post['no'].($onlyimgdel?' (file only)':''), valid());
		}
	}
	if($search_flag){
		if(!$onlyimgdel) $PMS->useModuleMethods('PostOnDeletion', array($delposts, 'frontend')); // "PostOnDeletion" Hook Point
		$files = $onlyimgdel ? $PIO->removeAttachments($delposts) : $PIO->removePosts($delposts);
		$FileIO->updateStorageSize(-$FileIO->deleteImage($files)); // 更新容量快取
		deleteCache($delposts);
		$PIO->dbCommit();
	}else error(_T('del_wrongpwornotfound'));
	updatelog();
	if(isset($_POST['func']) && $_POST['func'] == 'delete'){ // 前端管理刪除文章返回管理頁面
		if(isset($_SERVER['HTTP_REFERER'])){
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.$_SERVER['HTTP_REFERER']);
		}
		exit();
	}
}

/* 管理文章模式 */
function admindel(&$dat){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$pass = $_POST['pass']??''; // 管理者密碼
	$page = $_REQUEST['page']??0; // 切換頁數
	$onlyimgdel = $_POST['onlyimgdel']??''; // 只刪圖
	$modFunc = '';
	$delno = $thsno = array();
	$message = ''; // 操作後顯示訊息

	// 刪除文章區塊
	$delno = array_merge($delno, $_POST['clist']??array());
	if($delno) logtime("Delete post No.$delno".($onlyimgdel?' (file only)':''), valid());
	if($onlyimgdel != 'on') $PMS->useModuleMethods('PostOnDeletion', array($delno, 'backend')); // "PostOnDeletion" Hook Point
	$files = ($onlyimgdel != 'on') ? $PIO->removePosts($delno) : $PIO->removeAttachments($delno);
	$FileIO->updateStorageSize(-$FileIO->deleteImage($files));
	deleteCache($delno);
	$PIO->dbCommit();

	$line = $PIO->fetchPostList(0, $page * ADMIN_PAGE_DEF, ADMIN_PAGE_DEF); // 分頁過的文章列表
	$posts_count = count($line); // 迴圈次數
	$posts = $PIO->fetchPosts($line); // 文章內容陣列

	$dat.= '<form action="'.PHP_SELF.'" method="POST">';
	$dat.= '<input type="hidden" name="mode" value="admin" />
<input type="hidden" name="admin" value="del" />
<div align="left">'._T('admin_notices').'</div>'.
$message.'<br />
<center><table width="95%" cellspacing="0" cellpadding="0" border="1" class="postlists">
<thead><tr>'._T('admin_list_header').'</tr></thead>
<tbody>';

	for($j = 0; $j < $posts_count; $j++){
		$bg = ($j % 2) ? 'row1' : 'row2'; // 背景顏色
		extract($posts[$j]);

		// 修改欄位樣式
		//$now = preg_replace('/.{2}\/(.{5})\(.+?\)(.{5}).*/', '$1 $2', $now);
		$name = htmlspecialchars(str_cut(html_entity_decode(strip_tags($name)), 8));
		$sub = htmlspecialchars(str_cut(html_entity_decode($sub), 8));
		if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		$com = str_replace('<br />',' ',$com);
		$com = htmlspecialchars(str_cut(html_entity_decode($com), 20));

		// 討論串首篇停止勾選框 及 模組功能
		$modFunc = ' ';
		$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$j], $resto)); // "AdminList" Hook Point
		if($resto==0){ // $resto = 0 (即討論串首篇)
			$flgh = $PIO->getPostStatus($status);
		}

		// 從記錄抽出附加圖檔使用量並生成連結
		if($ext && $FileIO->imageExists($tim.$ext)){
			$clip = '<a href="'.$FileIO->getImageURL($tim.$ext).'" target="_blank">'.$tim.$ext.'</a>';
			$size = $FileIO->getImageFilesize($tim.$ext);
			$thumbName = $FileIO->resolveThumbName($tim);
			if($thumbName != false) $size += $FileIO->getImageFilesize($thumbName);
		}else{
			$clip = $md5chksum = '--';
			$size = 0;
		}

		if (valid() <= LEV_JANITOR) {
			$host = " - ";
		}

		// 印出介面
		$dat.= <<< _ADMINEOF_
<tr align="LEFT">
	<th align="center">$modFunc</th><th><input type="checkbox" name="clist[]" value="$no" />$no</th>
	<td><small class="time">$now</small></td>
	<td><b class="title">$sub</b></td>
	<td><b class="name">$name</b></td>
	<td><small>$com</small></td>
	<td>$host</td>
	<td align="center">$clip ($size)<br />$md5chksum</td>
</tr>
_ADMINEOF_;
	}
	$dat.= '</tbody></table>
		<p>
			<input type="submit" value="'._T('admin_submit_btn').'" /> <input type="reset" value="'._T('admin_reset_btn').'" /> [<label><input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" />'._T('del_img_only').'</label>]
		</p>
		<p>'._T('admin_totalsize', $FileIO->getCurrentStorageSize()).'</p>
</center></form>
<hr size="1" />';

	$countline = $PIO->postCount(); // 總文章數
	$page_max = ceil($countline / ADMIN_PAGE_DEF) - 1; // 總頁數
	$dat.= '<table id="pager" border="1" cellspacing="0" cellpadding="0"><tbody><tr>';
	if($page) $dat.= '<td><a href="'.PHP_SELF.'?mode=admin&admin=del&page='.($page - 1).'">'._T('prev_page').'</a></td>';
	else $dat.= '<td nowrap="nowrap">'._T('first_page').'</td>';
	$dat.= '<td>';
	for($i = 0; $i <= $page_max; $i++){
		if($i==$page) $dat.= '[<b>'.$i.'</b>] ';
		else $dat.= '[<a href="'.PHP_SELF.'?mode=admin&admin=del&page='.$i.'">'.$i.'</a>] ';
	}
	$dat.= '</td>';
	if($page < $page_max) $dat.= '<td><a href="'.PHP_SELF.'?mode=admin&admin=del&page='.($page + 1).'">'._T('next_page').'</a></td>';
	else $dat.= '<td nowrap="nowrap">'._T('last_page').'</td>';
	$dat.= '</tr></tbody></table>';
}

/**
 * 計算目前附加圖檔使用容量 (單位：KB)
 * @deprecated Use FileIO->getCurrentStorageSize() / FileIO->updateStorageSize($delta) instead
 */
function total_size($delta=0){
	$FileIO = PMCLibrary::getFileIOInstance();
	return $FileIO->getCurrentStorageSize($delta);
}

/* 搜尋(全文檢索)功能 */
function search(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	if(!USE_SEARCH) error(_T('search_disabled'));
	$searchKeyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : ''; // 欲搜尋的文字
	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>]';
	$level = valid();
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'search',$level));
	$dat .= $links.'<center class="theading2"><b>'._T('search_top').'</b></center>
</div>
';
	echo $dat;
	if($searchKeyword==''){
		echo '<form action="'.PHP_SELF.'" method="post">
<div id="search">
<input type="hidden" name="mode" value="search" />
';
		echo '<ul>'._T('search_notice').'<input type="text" name="keyword" size="30" />
'._T('search_target').'<select name="field"><option value="com" selected="selected">'._T('search_target_comment').'</option><option value="name">'._T('search_target_name').'</option><option value="sub">'._T('search_target_topic').'</option><option value="no">'._T('search_target_number').'</option></select>
'._T('search_method').'<select name="method"><option value="AND" selected="selected">'._T('search_method_and').'</option><option value="OR">'._T('search_method_or').'</option></select>
<input type="submit" value="'._T('search_submit_btn').'" />
</li>
</ul>
</div>
</form>';
	}else{
		$searchField = $_POST['field']; // 搜尋目標 (no:編號, name:名稱, sub:標題, com:內文)
		$searchMethod = $_POST['method']; // 搜尋方法
		$searchKeyword = preg_split('/(　| )+/', trim($searchKeyword)); // 搜尋文字用空格切割
		if ($searchMethod=='REG') $searchMethod = 'AND';
		$hitPosts = $PIO->searchPost($searchKeyword, $searchField, $searchMethod); // 直接傳回符合的文章內容陣列

		echo '<div id="searchresult">';
		$resultlist = '';
		foreach($hitPosts as $post){
			extract($post);
			if(USE_CATEGORY){
				$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
				$ary_category_count = count($ary_category);
				$ary_category2 = array();
				for($p = 0; $p < $ary_category_count; $p++){
					if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&c='.urlencode($c).'">'.$c.'</a>';
				}
				$category = implode(', ', $ary_category2);
			}else $category = '';
			$arrLabels = array('{$NO}'=>'<a href="'.PHP_SELF.'?res='.($resto?$resto.'#p'.$no:$no).'">'.$no.'</a>', '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'));
			$resultlist .= $PTE->ParseBlock('SEARCHRESULT',$arrLabels);
		}
		echo $resultlist ? $resultlist : '<center>'._T('search_notfound').'<br/>[<a href="?mode=search">'._T('search_back').'</a>]</center>';
		echo "</div>";
	}
	echo "</body></html>";
}

/* 利用類別標籤搜尋符合的文章 */
function searchCategory(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$category = isset($_GET['c']) ? strtolower(strip_tags(trim($_GET['c']))) : ''; // 搜尋之類別標籤
	if(!$category) error(_T('category_nokeyword'));
	$category_enc = urlencode($category); $category_md5 = md5($category);
	$page = isset($_GET['p']) ? @intval($_GET['p']) : 1; if($page < 1) $page = 1; // 目前瀏覽頁數
	$isrecache = isset($_GET['recache']); // 是否強制重新生成快取

	// 利用Session快取類別標籤出現篇別以減少負擔
	if(!isset($_SESSION['loglist_'.$category_md5]) || $isrecache){
		$loglist = $PIO->searchCategory($category);
		$_SESSION['loglist_'.$category_md5] = serialize($loglist);
	}else $loglist = unserialize($_SESSION['loglist_'.$category_md5]);

	$loglist_count = count($loglist);
	$page_max = ceil($loglist_count / PAGE_DEF); if($page > $page_max) $page = $page_max; // 總頁數

	// 分割陣列取出適當範圍作分頁之用
	$loglist_cut = array_slice($loglist, PAGE_DEF * ($page - 1), PAGE_DEF); // 取出特定範圍文章
	$loglist_cut_count = count($loglist_cut);

	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>] [<a href="'.PHP_SELF.'?mode=category&c='.$category_enc.'&recache=1">'._T('category_recache').'</a>]';
	$level = valid();
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'category',$level));
	$dat .= "<div>$links</div>\n";
	for($i = 0; $i < $loglist_cut_count; $i++){
		$posts = $PIO->fetchPosts($loglist_cut[$i]); // 取得文章內容
		$dat .= arrangeThread($PTE, ($posts[0]['resto'] ? $posts[0]['resto'] : $posts[0]['no']), null, $posts, 0, $loglist_cut[$i], array(), array(), false, false, false); // 逐個輸出 (引用連結不顯示)
	}

	$dat .= '<table id="pager" border="1"><tr>';
	if($page > 1) $dat .= '<td><form action="'.PHP_SELF.'?mode=category&c='.$category_enc.'&p='.($page - 1).'" method="post"><div><input type="submit" value="'._T('prev_page').'" /></div></form></td>';
	else $dat .= '<td nowrap="nowrap">'._T('first_page').'</td>';
	$dat .= '<td>';
	for($i = 1; $i <= $page_max ; $i++){
		if($i==$page) $dat .= "[<b>".$i."</b>] ";
		else $dat .= '[<a href="'.PHP_SELF.'?mode=category&c='.$category_enc.'&p='.$i.'">'.$i.'</a>] ';
	}
	$dat .= '</td>';
	if($page < $page_max) $dat .= '<td><form action="'.PHP_SELF.'?mode=category&c='.$category_enc.'&p='.($page + 1).'" method="post"><div><input type="submit" value="'._T('next_page').'" /></div></form></td>';
	else $dat .= '<td nowrap="nowrap">'._T('last_page').'</td>';
	$dat .= '</tr></table>';

	foot($dat);
	echo $dat;
}

/* 顯示已載入模組資訊 */
function listModules(){
	$PMS = PMCLibrary::getPMSInstance();

	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>]';
	$level = valid();
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'modules',$level));
	$dat .= $links.'<center class="theading2"><b>'._T('module_info_top').'</b></center>
</div>

<div id="modules">
';
	/* Module Loaded */
	$dat .= _T('module_loaded').'<ul>';
	foreach($PMS->getLoadedModules() as $m) $dat .= '<li>'.$m."</li>\n";
	$dat .= "</ul><hr size='1' />\n";

	/* Module Infomation */
	$dat .= _T('module_info').'<ul>';
	foreach($PMS->moduleInstance as $m) $dat .= '<li>'.$m->getModuleName().'<div>'.$m->getModuleVersionInfo()."</div></li>\n";
	$dat .= '</ul><hr size="1" />
</div>

';
	foot($dat);
	echo $dat;
}

/* 刪除舊頁面快取檔 */
function deleteCache($no){
	foreach($no as $n){
		if($oldCaches = glob('./cache/'.$n.'-*')){
			foreach($oldCaches as $o) @unlink($o);
		}
	}
}

/* 顯示系統各項資訊 */
function showstatus(){
	global $LIMIT_SENSOR, $THUMB_SETTING;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$countline = $PIO->postCount(); // 計算投稿文字記錄檔目前資料筆數
	$counttree = $PIO->threadCount(); // 計算樹狀結構記錄檔目前資料筆數
	$tmp_total_size = $FileIO->getCurrentStorageSize(); // 附加圖檔使用量總大小
	$tmp_ts_ratio = STORAGE_MAX > 0 ? $tmp_total_size / STORAGE_MAX : 0; // 附加圖檔使用量

	// 決定「附加圖檔使用量」提示文字顏色
  	if($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
	elseif($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
	elseif($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
	elseif($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
	else $clrflag_sl = 'F2004A';

	// 生成預覽圖物件資訊及功能是否正常
	$func_thumbWork = '<span class="offline">'._T('info_nonfunctional').'</span>';
	$func_thumbInfo = '(No thumbnail)';
	if(USE_THUMB !== 0){
		$thumbType = USE_THUMB; if(USE_THUMB==1){ $thumbType = 'gd'; }
		require(ROOTPATH.'lib/thumb/thumb.'.$thumbType.'.php');
		$thObj = new ThumbWrapper();
		if($thObj->isWorking()) $func_thumbWork = '<span class="online">'._T('info_functional').'</span>';
		$func_thumbInfo = $thObj->getClass();
		unset($thObj);
	}

	// PIOSensor
	if(count($LIMIT_SENSOR))
		$piosensorInfo=nl2br(PIOSensor::info($LIMIT_SENSOR));

	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>] [<a href="'.PHP_SELF.'?mode=moduleloaded">'._T('module_info_top').'</a>]';
	$level = valid();
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'status',$level));
	$dat .= $links.'<center class="theading2"><b>'._T('info_top').'</b></center>
</div>
';

	$dat .= '
<center id="status">
	<table cellspacing="0" cellpadding="0" border="1"><thead>
		<tr><th colspan="4">'._T('info_basic').'</th></tr>
	</thead><tbody>
		<tr><td width="240">'._T('info_basic_ver').'</td><td colspan="3"> '.PIXMICAT_VER.' </td></tr>
		<tr><td>'._T('info_basic_pio').'</td><td colspan="3"> '.PIXMICAT_BACKEND.' : '.$PIO->pioVersion().'</td></tr>
		<tr><td>'._T('info_basic_threadsperpage').'</td><td colspan="3"> '.PAGE_DEF.' '._T('info_basic_threads').'</td></tr>
		<tr><td>'._T('info_basic_postsperpage').'</td><td colspan="3"> '.RE_DEF.' '._T('info_basic_posts').'</td></tr>
		<tr><td>'._T('info_basic_postsinthread').'</td><td colspan="3"> '.RE_PAGE_DEF.' '._T('info_basic_posts').' '._T('info_basic_posts_showall').'</td></tr>
		<tr><td>'._T('info_basic_bumpposts').'</td><td colspan="3"> '.MAX_RES.' '._T('info_basic_posts').' '._T('info_basic_0disable').'</td></tr>
		<tr><td>'._T('info_basic_bumphours').'</td><td colspan="3"> '.MAX_AGE_TIME.' '._T('info_basic_hours').' '._T('info_basic_0disable').'</td></tr>
		<tr><td>'._T('info_basic_urllinking').'</td><td colspan="3"> '.AUTO_LINK.' '._T('info_0no1yes').'</td></tr>
		<tr><td>'._T('info_basic_com_limit').'</td><td colspan="3"> '.COMM_MAX._T('info_basic_com_after').'</td></tr>
		<tr><td>'._T('info_basic_anonpost').'</td><td colspan="3"> '.ALLOW_NONAME.' '._T('info_basic_anonpost_opt').'</td></tr>
		<tr><td>'._T('info_basic_del_incomplete').'</td><td colspan="3"> '.KILL_INCOMPLETE_UPLOAD.' '._T('info_0no1yes').'</td></tr>
		<tr><td>'._T('info_basic_use_sample', $THUMB_SETTING['Quality']).'</td><td colspan="3"> '.USE_THUMB.' '._T('info_0notuse1use').'</td></tr>
		<tr><td>'._T('info_basic_useblock').'</td><td colspan="3"> '.BAN_CHECK.' '._T('info_0disable1enable').'</td></tr>
		<tr><td>'._T('info_basic_showid').'</td><td colspan="3"> '.DISP_ID.' '._T('info_basic_showid_after').'</td></tr>
		<tr><td>'._T('info_basic_cr_limit').'</td><td colspan="3"> '.BR_CHECK._T('info_basic_cr_after').'</td></tr>
		<tr><td>'._T('info_basic_timezone').'</td><td colspan="3"> GMT '.TIME_ZONE.'</td></tr>
		<tr><td>'._T('info_basic_theme').'</td><td colspan="3"> '.$PTE->BlockValue('THEMENAME').' '.$PTE->BlockValue('THEMEVER').'<br/>by '.$PTE->BlockValue('THEMEAUTHOR').'</td></tr>
		<tr><th colspan="4">'._T('info_dsusage_top').'</th></tr>
		<tr align="center"><td>'._T('info_basic_threadcount').'</td><td colspan="'.(isset($piosensorInfo)?'2':'3').'"> '.$counttree.' '._T('info_basic_threads').'</td>'.(isset($piosensorInfo)?'<td rowspan="2">'.$piosensorInfo.'</td>':'').'</tr>
		<tr align="center"><td>'._T('info_dsusage_count').'</td><td colspan="'.(isset($piosensorInfo)?'2':'3').'">'.$countline.'</td></tr>
		<tr><th colspan="4">'._T('info_fileusage_top').STORAGE_LIMIT.' '._T('info_0disable1enable').'</th></tr>';

	if(STORAGE_LIMIT){
		$dat .= '
		<tr align="center"><td>'._T('info_fileusage_limit').'</td><td colspan="2">'.STORAGE_MAX.' KB</td><td rowspan="2">'._T('info_dsusage_usage').'<br /><font color="#'.$clrflag_sl.'">'.substr(($tmp_ts_ratio * 100), 0, 6).'</font> %</td></tr>
		<tr align="center"><td>'._T('info_fileusage_count').'</td><td colspan="2"><font color="#'.$clrflag_sl.'">'.$tmp_total_size.' KB</font></td></tr>';
	}else{
		$dat .= '
		<tr align="center"><td>'._T('info_fileusage_count').'</td><td>'.$tmp_total_size.' KB</td><td colspan="2">'._T('info_dsusage_usage').'<br /><span class="green">'._T('info_fileusage_unlimited').'</span></td></tr>';
	}

	$dat .= '
		<tr><th colspan="4">'._T('info_server_top').'</th></tr>
		<tr align="center"><td colspan="3">'.$func_thumbInfo.'</td><td>'.$func_thumbWork.'</td></tr>
	</tbody></table>
	<hr size="1" />
</center>';

	foot($dat);
	echo $dat;
}

function actionlog(&$dat) {
	$LIMIT = 40;
	$page = intval($_REQUEST['page']??0);
	$offset = $page*$LIMIT;
	// filter
	$filter = $_REQUEST['filter']??'';
	$ipfilter = preg_quote($_REQUEST['ipfilter']??'');
	$dat.= '<p align="LEFT"><form action="'.PHP_SELF.'" method="GET">
	<input type="hidden" name="mode" value="admin" />
	<input type="hidden" name="admin" value="action" />
	<input type="hidden" name="page" value="'.$page.'" />
	<select name="filter">
		<option'.($filter==''?' selected="selected"':'').' value="">All actions</option>
		<option'.($filter=='system'?' selected="selected"':'').' value="system">System actions only</option>
		<option'.($filter=='user'?' selected="selected"':'').' value="user">User actions only</option>
		<option'.($filter=='moderator'?' selected="selected"':'').' value="moderator">Moderator actions only</option>
		<option'.($filter=='janitor'?' selected="selected"':'').' value="janitor">## Janitor actions only</option>
		<option'.($filter=='mod'?' selected="selected"':'').' value="mod">## Mod actions only</option>
		<option'.($filter=='admin'?' selected="selected"':'').' value="admin">## Admin actions only</option>
	</select><input type="submit" value="Filter" /><br />
	<label>IP Addr:<input class="textinput" type="text" name="ipfilter" value="'.($_REQUEST['ipfilter']??'').'" /></label>
</form>';
	switch ($filter) {
		case 'user':
			$regex = '^USER';
			break;
		case 'system':
			$regex = '^SYSTEM';
			break;
		case 'moderator':
			$regex = '^(JANITOR|MOD|ADMIN)';
			break;
		case 'janitor':
			$regex = '^JANITOR';
			break;
		case 'mod':
			$regex = '^MOD';
			break;
		case 'admin':
			$regex = '^ADMIN';
			break;
		default:
			$regex = '';
			break;
	}
	if ($ipfilter) $regex.= "\s\($ipfilter\)";
	// log
	$dat.= '<pre class="actionlog">';
	$log = array_reverse(file(STORAGE_PATH.ACTION_LOG));
	$log = array_filter($log, function ($a) use ($regex) { return preg_match("/$regex/", $a); });
	$log = array_values($log);
	$find = false;
	for ($i=$offset; $i<$offset+$LIMIT; $i++) {
		if (!isset($log[$i])) continue;
		$dat.= $log[$i];
		$find = true;
	}
	if (!$find) $dat.= 'No result found with specified filter.';
	$dat.= '</pre>';
	// pager
	$dat.= '<table id="pager" cellspacing="0" cellpadding="0" border="1"><tbody><tr>';
	if ($page) $dat.= '<td><a href="'.PHP_SELF.'?mode=admin&admin=action&page='.($page-1).'&filter='.$filter.'">Prev</a></td>';
	else $dat.= '<td>First</td>';
	$dat.= '<td>';
	for ($i=0; $i<count($log); $i+=$LIMIT) {
		$p = $i/$LIMIT;
		if ($p==$page) $dat.= '[<b>'.($p+1).'</b>]';
		else $dat.= '[<a href="'.PHP_SELF.'?mode=admin&admin=action&page='.$p.'&filter='.$filter.'&">'.($p+1).'</a>]';
	}
	$dat.= '</td>';
	if ($offset<count($log)-$LIMIT) $dat.= '<td><a href="'.PHP_SELF.'?mode=admin&admin=action&page='.($page+1).'&filter='.$filter.'">Next</a></td>';
	else $dat.= '<td>Last</td>';
	$dat.= '</tr></tbody></table><br clear="ALL" />';
}

function logout(&$dat) {
	unset($_SESSION['kokologin']);
	redirect(fullURL().PHP_SELF2.'?'.$_SERVER['REQUEST_TIME']);
	exit;
}

/*-----------程式各項功能主要判斷-------------*/
if(GZIP_COMPRESS_LEVEL && ($Encoding = CheckSupportGZip())){ ob_start(); ob_implicit_flush(0); } // 支援且開啟Gzip壓縮就設緩衝區
$mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_POST['mode']) ? $_POST['mode'] : ''); // 目前執行模式 (GET, POST)

switch($mode){
	case 'regist':
		regist();
		break;
	case 'preview':
		if(!USE_PREVIEW) error('ERROR: Posting preview is not enabled on this board.');
		regist(true);
		break;
	case 'admin':
		if ($pass=$_POST['pass']??'')
			$_SESSION['kokologin'] = $pass;
		$level = valid($pass);
		$admin = $_REQUEST['admin']??'';
		$dat = '';
		head($dat);
		$links = '[<a href="'.PHP_SELF2.'?'.$_SERVER['REQUEST_TIME'].'">Return</a>] [<a href="'.PHP_SELF.'?mode=rebuild">Rebuild</a>] [<a href="'.PHP_SELF.'?pagenum=0">Live Frontend</a>]';
		$PMS->useModuleMethods('LinksAboveBar', array(&$dat,'admin',$level));
		$dat.= "$links<center class=\"theading3\"><b>Administrator mode</b></center>";
		$dat.= '<center><form action="'.PHP_SELF.'" method="POST" name="adminform">';
		$admins = array(
			array('name'=>'del', 'level'=>LEV_JANITOR, 'label'=>'Manage posts', 'func'=>'admindel'),
			array('name'=>'action', 'level'=>LEV_ADMIN, 'label'=>'Action log', 'func'=>'actionlog'),
			array('name'=>'optimize', 'level'=>LEV_ADMIN, 'label'=>'Optimize', 'func'=>''),
			array('name'=>'check', 'level'=>LEV_ADMIN, 'label'=>'Check data source', 'func'=>''),
			array('name'=>'repair', 'level'=>LEV_ADMIN, 'label'=>'Repair data source', 'func'=>''),
			array('name'=>'export', 'level'=>LEV_ADMIN, 'label'=>'Export data', 'func'=>''),
			array('name'=>'logout', 'level'=>LEV_JANITOR, 'label'=>'Logout', 'func'=>'logout'),
		);
		$dat.= '<nobr>';
		foreach ($admins as $adminmode) {
			if ($level==LEV_NONE && $adminmode['name']=='logout') continue;
			$checked = ($admin==$adminmode['name']) ? ' checked="checked"' : '';
			$dat.= '<label><input type="radio" name="admin" value="'.$adminmode['name'].'"'.$checked.' />'.$adminmode['label'].'</label> ';
		}
		$dat.= '</nobr>';
		if ($level==LEV_NONE) {
			$dat.= '<br/>
	<input class="inputtext" type="password" name="pass" value="" size="8" /><button type="submit" name="mode" value="admin">Login</button>
</form></center><hr/>';
			foot($dat);
			die($dat.'</body></html>');
		} else {
			$dat.= '<button type="submit" name="mode" value="admin">Submit</button></form></center>';
		}
		$find = false;
		foreach ($admins as $adminmode) {
			if ($admin!=$adminmode['name']) continue;
			$find = true;
			if ($adminmode['level']>$level) {
				$dat.= '<center><b class="error">ERROR: No Access.</b></center><hr size="1" />';
				break;
			}
			if ($adminmode['func']) {
				$adminmode['func']($dat, $admin);
			} else {
				if(!$PIO->dbMaintanence($admin)) $dat.= '<center><b class="error">ERROR: Backend does not support this operation.</b></center><hr size="1" />';
				else $dat.= '<center>'.(($mret=$PIO->dbMaintanence($admin,true))
					? '<b class="good">Success!</b>'
					: '<b class="error">Failure!</b>').
					(is_bool($mret)?'':"<br />$mret<hr size='1' />").'</center>';
			}
		}
		if (!$find) $dat.= '<hr size="1" />';
		foot($dat);
		die($dat.'</body></html>');
		break;
	case 'search':
		search();
		break;
	case 'status':
		showstatus();
		break;
	case 'category':
		searchCategory();
		break;
	case 'module':
		$PMS = PMCLibrary::getPMSInstance();
		$load = $_GET['load']??$_POST['load']??'';
		if($PMS->onlyLoad($load)) $PMS->moduleInstance[$load]->ModulePage();
		else error("Module Not Found(".htmlspecialchars($load).")");
		break;
	case 'moduleloaded':
		listModules();
		break;
	case 'usrdel':
		usrdel();
	case 'rebuild':
		if (valid()>=LEV_JANITOR) {
			logtime("Rebuilt pages", valid());
			updatelog();
			if(THREAD_PAGINATION){
				if($oldCaches = glob(STORAGE_PATH.'cache/*')){
					foreach($oldCaches as $o) unlink($o); // Clear old catalog caches
				}
			}
		}
		header('HTTP/1.1 302 Moved Temporarily');
		header('Location: '.fullURL().PHP_SELF2.'?'.time());
		break;
	default:
		header('Content-Type: text/html; charset=utf-8');

		$res = isset($_GET['res']) ? $_GET['res'] : 0; // 欲回應編號
		if($res){ // 回應模式輸出
			$page = $_GET['pagenum']??'RE_PAGE_MAX';
			if(!($page=='all' || $page=='RE_PAGE_MAX')) $page = intval($_GET['pagenum']);
			updatelog($res, $page); // 實行分頁
		}elseif(isset($_GET['pagenum']) && intval($_GET['pagenum']) > -1){ // PHP動態輸出一頁
			updatelog(0, intval($_GET['pagenum']));
		}else{ // 導至靜態庫存頁
			if(!is_file(PHP_SELF2)) {
				logtime("Rebuilt pages");
				updatelog();
			}
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.fullURL().PHP_SELF2.'?'.$_SERVER['REQUEST_TIME']);
		}
}
if(GZIP_COMPRESS_LEVEL && $Encoding){ // 有啟動Gzip
	if(!ob_get_length()) exit; // 沒內容不必壓縮
	header('Content-Encoding: '.$Encoding);
	header('X-Content-Encoding-Level: '.GZIP_COMPRESS_LEVEL);
	header('Vary: Accept-Encoding');
	print gzencode(ob_get_clean(), GZIP_COMPRESS_LEVEL); // 壓縮內容
}
clearstatcache();
