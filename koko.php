<?php

define("PIXMICAT_VER", 'Koko BBS Release 1'); // Version information text
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/


@session_start();

require './config.php'; // Introduce a settings file
require ROOTPATH.'lib/pmclibrary.php'; // Ingest libraries
require ROOTPATH.'lib/lib_errorhandler.php'; // Introduce global error capture
require ROOTPATH.'lib/lib_compatible.php'; // Introduce compatible libraries
require ROOTPATH.'lib/lib_common.php'; // Introduce common function archives
require ROOTPATH.'lib/lib_admin.php'; // Admin panel functions
require ROOTPATH.'lib/lib_template.php'; // Template library
require ROOTPATH.'lib/lib_cache.php'; // Caching functions
require ROOTPATH.'lib/lib_post.php'; // Post and thread functions

defined("ROLL") or define("ROLL",[]);//When undefined, empty array

/* Update the log file/output thread */ 
function updatelog($resno=0,$pagenum=-1,$single_page=false, $last=-1){
	global $LIMIT_SENSOR;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$AccountIO = PMCLibrary::getAccountIOInstance();
	$pagenum = intval($pagenum);

	$adminMode = $AccountIO->valid()>=LEV_JANITOR && $pagenum != -1 && !$single_page; // Front-end management mode

	$resno = intval($resno); // Number digitization
	$page_start = $page_end = 0; // Static page number
	$inner_for_count = 1; // The number of inner loop executions
	$RES_start = $RES_amount = $hiddenReply = $tree_count = 0;
	$kill_sensor = $old_sensor = false; // Predictive system start flag
	$arr_kill = $arr_old = array(); // Obsolete numbered array
	$pte_vals = array('{$THREADFRONT}'=>'','{$THREADREAR}'=>'','{$SELF}'=>PHP_SELF,
		'{$DEL_HEAD_TEXT}' => '<input type="hidden" name="mode" value="usrdel" />'._T('del_head'),
		'{$DEL_IMG_ONLY_FIELD}' => '<input type="checkbox" name="onlyimgdel" id="onlyimgdel" value="on" />',
		'{$DEL_IMG_ONLY_TEXT}' => _T('del_img_only'),
		'{$DEL_PASS_TEXT}' => ($adminMode ? '<input type="hidden" name="func" value="delete" />' : '')._T('del_pass'),
		'{$DEL_PASS_FIELD}' => '<input type="password" name="pwd" size="8" value="" />',
		'{$DEL_SUBMIT_BTN}' => '<input type="submit" value="'._T('del_btn').'" />',
		'{$IS_THREAD}' => !!$resno);
	if($resno) $pte_vals['{$RESTO}'] = $resno;

	if(!$resno){
		if($pagenum==-1){ // Rebuild mode (PHP dynamic output of multiple pages)
			$threads = $PIO->fetchThreadList(); // Get a full list of discussion threads
			$PMS->useModuleMethods('ThreadOrder', array($resno,$pagenum,$single_page,&$threads)); // "ThreadOrder" Hook Point
			$threads_count = count($threads);
			$inner_for_count = $threads_count > PAGE_DEF ? PAGE_DEF : $threads_count;
			$page_end = ($last == -1 ? ceil($threads_count / PAGE_DEF) : $last);
		}else{ // Discussion of the clue label pattern (PHP dynamic output one page)
			$threads_count = $PIO->threadCount(); // Discuss the number of strings
			if($pagenum < 0 || ($pagenum * PAGE_DEF) >= $threads_count) error(_T('page_not_found')); // $Pagenum is out of range
			$page_start = $page_end = $pagenum; // Set a static page number
			$threads = $PIO->fetchThreadList(); // Get a full list of discussion threads
			$PMS->useModuleMethods('ThreadOrder', array($resno,$pagenum,$single_page,&$threads)); // "ThreadOrder" Hook Point
			$threads = array_splice($threads, $pagenum * PAGE_DEF, PAGE_DEF); // Remove the list of discussion threads after the tag
			$inner_for_count = count($threads); // The number of discussion strings is the number of cycles
		}
	}else{
		if(!$PIO->isThread($resno)){ // Try to find the thread by child post no. instead
			$resnoNew = $PIO->fetchPosts($resno)[0]['resto'];
			if (!$PIO->isThread($resnoNew)) error(_T('thread_not_found'));
			header("Location: ".fullURL().PHP_SELF."?res=".$resnoNew."&q=".$resno."#p".$resno); // Found, redirect
		}
		$AllRes = isset($pagenum) && ($_GET['pagenum']??'')=='all'; // Whether to use ALL for output

		// Calculate the response label range
		$tree_count = $PIO->postCount($resno) - 1; // Number of discussion thread responses
		if($tree_count && RE_PAGE_DEF){ // There is a response and RE_PAGE_DEF > 0 to do the pagination action
			if($pagenum==='all'){ // show all
				$pagenum = 0;
				$RES_start = 1; $RES_amount = $tree_count;
			}else{
				if($pagenum==='RE_PAGE_MAX') $pagenum = ceil($tree_count / RE_PAGE_DEF) - 1; // Special value: Last page
				if($pagenum < 0) $pagenum = 0; // negative number
				if($pagenum * RE_PAGE_DEF >= $tree_count) error(_T('page_not_found'));
				$RES_start = $pagenum * RE_PAGE_DEF + 1; // Begin
				$RES_amount = RE_PAGE_DEF; // Take several
			}
		}elseif($pagenum > 0) error(_T('page_not_found')); // In the case of no response, only pagenum = 0 or negative numbers are allowed
		else{ $RES_start = 1; $RES_amount = $tree_count; $pagenum = 0; } // Output All Responses

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

	// Predict that old articles will be deleted and archives
	$tmp_total_size = $FileIO->getCurrentStorageSize(); // The current usage of additional image files
	$tmp_STORAGE_MAX = STORAGE_MAX * (($tmp_total_size >= STORAGE_MAX) ? 1 : 0.95); // Estimated upper limit
	if(STORAGE_LIMIT && STORAGE_MAX > 0 && ($tmp_total_size >= $tmp_STORAGE_MAX)){
		$kill_sensor = true; // tag opens
		$arr_kill = $PIO->delOldAttachments($tmp_total_size, $tmp_STORAGE_MAX); // Outdated attachment array
	}

	$PMS->useModuleMethods('ThreadFront', array(&$pte_vals['{$THREADFRONT}'], $resno)); // "ThreadFront" Hook Point
	$PMS->useModuleMethods('ThreadRear', array(&$pte_vals['{$THREADREAR}'], $resno)); // "ThreadRear" Hook Point

	// Generate static pages one page at a time
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
		// Output the thread content
		for($i = 0; $i < $inner_for_count; $i++){
			// Take out the thread number
			if($resno) $tID = $resno; // Single thread output (response mode)
			else{
				if($pagenum == -1 && ($page * PAGE_DEF + $i) >= $threads_count) break; // rebuild Exceeding the index indicates that it is all done
				$tID = ($page_start==$page_end) ? $threads[$i] : $threads[$page * PAGE_DEF + $i]; // One page of content (normal mode) / multi-page content (rebuild mode)
				$tree_count = $PIO->postCount($tID) - 1; // Number of discussion thread responses
				$RES_start = $tree_count - RE_DEF + 1; if($RES_start < 1) $RES_start = 1; // Begin
				$RES_amount = RE_DEF; // Take several
				$hiddenReply = $RES_start - 1; // The number of responses that are hidden
			}

			// $RES_start, $RES_amount Take it to calculate the new clue structure (after the tag, part of the response is hidden)
			$tree = $PIO->fetchPostList($tID); // The entire discussion is structured in a tree-like manner
			$tree_cut = array_slice($tree, $RES_start, $RES_amount); array_unshift($tree_cut, $tID); // Take out a specific range of responses
			$posts = $PIO->fetchPosts($tree_cut); // Get the article schema content
			$pte_vals['{$THREADS}'] .= arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno, $arr_kill, $arr_old, $kill_sensor, $old_sensor, true, $adminMode, $inner_for_count); // Leave this function to discuss serial printing
		}
		$pte_vals['{$PAGENAV}'] = '';

		// Page change judgment
		$prev = ($resno ? $pagenum : $page) - 1;
		$next = ($resno ? $pagenum : $page) + 1;
		if($resno){ // Response labels
			if(RE_PAGE_DEF > 0){ // The Responses tab is on
				$pte_vals['{$PAGENAV}'] .= '<table border="1" id="pager"><tbody><tr><td nowrap="nowrap">';
				$pte_vals['{$PAGENAV}'] .= ($prev >= 0) ? '<a rel="prev" href="'.PHP_SELF.'?res='.$resno.'&pagenum='.$prev.'">'._T('prev_page').'</a>' : _T('first_page');
				$pte_vals['{$PAGENAV}'] .= "</td><td>";
				if($tree_count==0) $pte_vals['{$PAGENAV}'] .= '[<b>0</b>] '; // No response
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
		}else{ // General labels
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
		// Archive / Output
		if($single_page || ($pagenum == -1 && !$resno)){ // Static cache page generation
			if(THREAD_PAGINATION){
				if($oldCaches = glob(STORAGE_PATH.'cache/catalog-*')){
					unlinkCache($oldCaches); // Clear old catalog caches
				}
				if($oldCaches = glob(STORAGE_PATH.'cache/api-0.*')){
					unlinkCache($oldCaches); // Clear old API caches
				}
			}
			if($page==0) $logfilename = PHP_SELF2;
			else $logfilename = $page.PHP_EXT;
			$fp = fopen($logfilename, 'w');
			stream_set_write_buffer($fp, 0);
			fwrite($fp, $dat);
			fclose($fp);
			@chmod($logfilename, 0666);
			if(STATIC_HTML_UNTIL != -1 && STATIC_HTML_UNTIL==$page) break; // Page Limit
		}else{ // PHP output (responsive mode/regular dynamic output)
			if(THREAD_PAGINATION && !$adminMode && $resno && !isset($_GET['upseries'])){ // Thread pagination
				if($oldCaches = glob(STORAGE_PATH.'cache/api-'.$resno.'.*')){
					unlinkCache($oldCaches); // Clear old API caches
				}
				if($oldCaches = glob($cacheFile.'*')){
					unlinkCache($oldCaches); // Clear old caches
				}
				@$fp = fopen($cacheGzipPrefix.$cacheFile.$cacheETag, 'w');
				if($fp) { // Write new caches
					createHtmlCache($fp, $dat, $cacheETag);
				}
			}
			echo $dat;
			break;
		}
	}
	
}

/* Output thread schema */
function arrangeThread($PTE, $tree, $tree_cut, $posts, $hiddenReply, $resno, $arr_kill, $arr_old, $kill_sensor, $old_sensor, $showquotelink=true, $adminMode=false, $threads_shown=0){
	$resno = isset($resno) && $resno ? $resno : 0;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$thdat = ''; // Discuss serial output codes
	$posts_count = count($posts); // Number of cycles
	if(gettype($tree_cut) == 'array') $tree_cut = array_flip($tree_cut); // array_flip + isset Search Law
	if(gettype($tree) == 'array') $tree_clone = array_flip($tree);
	// $i = 0 (first article), $i = 1~n (response)
	for($i = 0; $i < $posts_count; $i++){
		$imgsrc = $img_thumb = $imgwh_bar = '';
		$IMG_BAR = $REPLYBTN = $QUOTEBTN = $BACKLINKS = $POSTFORM_EXTRA = $WARN_OLD = $WARN_BEKILL = $WARN_ENDREPLY = $WARN_HIDEPOST = '';
		extract($posts[$i]); // Take out the thread content setting variable

		// Set the field value
		if(CLEAR_SAGE) $email = preg_replace('/^sage( *)/i', '', trim($email)); // Clear the "sage" keyword from the e-mail
		if(ALLOW_NONAME==2){ // Forced beheading
			if($email) $now = "<a href=\"mailto:$email\">$now</a>";
		}else{
			if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		}

		$com = quote_link($com);
		$com = quote_unkfunc($com);
		
		// Mark threads that hit age limit (this replaces the old system for marking old threads)
		if (!$i && MAX_AGE_TIME && $_SERVER['REQUEST_TIME'] - $time > (MAX_AGE_TIME * 60 * 60)) $com .= "<br><br><span class='warning'>"._T('warn_oldthread')."</span>";
		
		// Configure attachment display
		if ($ext) {
			if(!$fname) $fname = $tim;
			$truncated = (strlen($fname)>40 ? substr($fname,0,40).'(&hellip;)' : $fname);
			if ($fname=='SPOILERS') {
				$truncated=$fname;
			} else {
				$truncated.=$ext;
				$fname.=$ext;
			}

 			$fnameJS = str_replace('&#039;', '\&#039;', $fname);
 			$truncatedJS = str_replace('&#039;', '\&#039;', $truncated);
			$imageURL = $FileIO->getImageURL($tim.$ext); // image URL
			$thumbName = $FileIO->resolveThumbName($tim); // thumb Name
			$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.STATIC_URL.'image/nothumb.gif" class="postimg" alt="'.$imgsize.'" hspace="20" vspace="3" border="0" align="left" /></a>'; // Default display style (when no preview image)
			if($tw && $th){
				if ($thumbName != false){ // There is a preview image
					$thumbURL = $FileIO->getImageURL($thumbName); // thumb URL
//					$img_thumb = '<small>'._T('img_sample').'</small>';
					$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.$thumbURL.'" width="'.$tw.'" height="'.$th.'" class="postimg" alt="'.$imgsize.'" title="Click to show full image" hspace="20" vspace="3" border="0" align="left" /></a>';
				}
				if(SHOW_IMGWH) $imgwh_bar = ', '.$imgw.'x'.$imgh; // Displays the original length and width dimensions of the attached image file
			} else if ($ext = "swf") {
				$imgsrc = '<a href="'.$imageURL.'" target="_blank" rel="nofollow"><img src="'.SWF_THUMB.'" class="postimg" alt="SWF Embed" hspace="20" vspace="3" border="0" align="left" /></a>'; // Default display style (when no preview image)
			} else $imgsrc = '';
			$IMG_BAR = _T('img_filename').'<a href="'.$imageURL.'" target="_blank" rel="nofollow" onmouseover="this.textContent=\''.$fnameJS.'\';" onmouseout="this.textContent=\''.$truncatedJS.'\'"> '.$truncated.'</a> <a href="'.$imageURL.'" download="'.$fname.'"><div class="download"></div></a> <small>('.$imgsize.$imgwh_bar.')</small> '.$img_thumb;
		}

        // Set the response/reference link
        if(USE_QUOTESYSTEM) {
            $qu = $_GET['q']??''; if ($qu) $qu.= ',';
            if($resno){ // Response mode
                if($showquotelink) $QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree[0]."&q=".htmlspecialchars($qu)."".$no.'#postform" class="qu" title="Quote">'.strval($no).'</a>';
                else $QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree."&q=".htmlspecialchars($qu)."".$no.'#postform" title="Quote">'.strval($no).'</a>';
            }else{
                if(!$i)    $REPLYBTN = '[<a href="'.PHP_SELF.'?res='.$no.'">'._T('reply_btn').'</a>]'; // First article
                $QUOTEBTN = '<a href="'.PHP_SELF.'?res='.$tree[0]."&q=".htmlspecialchars($qu)."".$no.'#postform" title="Quote">'.$no.'</a>';
            }
            unset($qu);
			
		} else {
			if($resno&&!$i)		$REPLYBTN = '[<a href="'.PHP_SELF.'?res='.$no.'">'._T('reply_btn').'</a>]';
			$QUOTEBTN = $no;
		}

		if($adminMode){ // Front-end management mode
			$modFunc = '';
			$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$i], $resto)); // "AdminList" Hook Point
			$POSTFORM_EXTRA .= $modFunc;
		}

		// Set thread properties
		if(STORAGE_LIMIT && $kill_sensor) if(isset($arr_kill[$no])) $WARN_BEKILL = '<span class="warning">'._T('warn_sizelimit').'</span><br />'; // Predict to delete too large files
		if(!$i){ // 首篇 Only
			$flgh = $PIO->getPostStatus($status);
			if($hiddenReply) $WARN_HIDEPOST = '<span class="omittedposts">'._T('notice_omitted',$hiddenReply).'</span><br />'; // There is a hidden response
		}
		// Automatically link category labels
		if(USE_CATEGORY){
			$ary_category = explode(',', str_replace('&#44;', ',', $category)); $ary_category = array_map('trim', $ary_category);
			$ary_category_count = count($ary_category);
			$ary_category2 = array();
			for($p = 0; $p < $ary_category_count; $p++){
				if($c = $ary_category[$p]) $ary_category2[] = '<a href="'.PHP_SELF.'?mode=category&c='.urlencode($c).'">'.$c.'</a>';
			}
			$category = implode(', ', $ary_category2);
		}else $category = '';

		$THREADNAV = '<a href="#postform">&#9632;</a>&nbsp;
			      <a href="#top">&#9650;</a>&nbsp;
			      <a href="#bottom">&#9660;</a>&nbsp;
		';

		// Final output
		if($i){ // Response
			$arrLabels = bindReplyValuesToTemplate($no, $resto, $sub, $name, $now, $category, $QUOTEBTN, $IMG_BAR, $imgsrc, $WARN_BEKILL, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno);
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadReply', array(&$arrLabels, $posts[$i], $resno)); // "ThreadReply" Hook Point
			$thdat .= $PTE->ParseBlock('REPLY', $arrLabels);
		}else{ // First Article
			$arrLabels = bindOPValuesToTemplate($no, $sub, $name, $now, $category, $QUOTEBTN, $REPLYBTN, $IMG_BAR, $imgsrc, $WARN_OLD, $WARN_BEKILL, $WARN_ENDREPLY, $WARN_HIDEPOST, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno); 
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadPost', array(&$arrLabels, $posts[$i], $resno)); // "ThreadPost" Hook Point
			$thdat .= $PTE->ParseBlock('THREAD', $arrLabels);
		}
	}
	$thdat .= $PTE->ParseBlock('THREADSEPARATE',($resno)?array('{$RESTO}'=>$resno):array());
	return $thdat;
}

/* Write to post table */
function regist($preview=false){
	global $BAD_STRING, $BAD_FILEMD5, $BAD_IPADDR, $LIMIT_SENSOR;
    $PIO = PMCLibrary::getPIOInstance();
    $FileIO = PMCLibrary::getFileIOInstance();
    $PMS = PMCLibrary::getPMSInstance();
 	$AccountIO = PMCLibrary::getAccountIOInstance();
 
    $fname = '';
    $ext = '';
    $dest = '';
    $tmpfile = '';
    $mes = ''; 
    $up_incomplete = 0; 
    $is_admin = false;
    $delta_totalsize = 0; // The change in the total file size
 
    /* get post data */
    $name = CleanStr($_POST['name']??'');
    $email = CleanStr($_POST['email']??'');
    $sub = CleanStr($_POST['sub']??'');
    $com = $_POST['com']??'';
    $pwd = $_POST['pwd']??'';
    $category = CleanStr($_POST['category']??'');
    $resto = intval($_POST['resto']??0);
    $pwdc = $_COOKIE['pwdc']??'';
    $ip = getREMOTE_ADDR(); 
    $host = $ip;
    $time = $_SERVER['REQUEST_TIME'];
    $tim  = sprintf('%d%03d', $time = microtime(true), ($time - floor($time)) * 1000);
    $upfile = '';
    $upfile_path = '';
    $upfile_name = '';
    $upfile_status = 4;
    
    spamValidate($ip, $name, $email, $sub, $com);
    /* hook call */
	$PMS->useModuleMethods('RegistBegin', array(&$name, &$email, &$sub, &$com, array('file'=>&$upfile, 'path'=>&$upfile_path, 'name'=>&$upfile_name, 'status'=>&$upfile_status), array('ip'=>$ip, 'host'=>$host), $resto)); // "RegistBegin" Hook Point

    if(TEXTBOARD_ONLY == false) {
		processFiles($upfile, $upfile_path, $upfile_name, $upfile_status, $md5chksum, $imgW, $imgH, $imgsize, $W, $H, $fname, $ext, $age, $status, $resto, $tim, $preview, $dest, $tmpfile);
    }
     
    // Check the form field contents and trim them
    if(strlenUnicode($name) > INPUT_MAX)    error(_T('regist_nametoolong'), $dest);
    if(strlenUnicode($email) > INPUT_MAX)   error(_T('regist_emailtoolong'), $dest);
    if(strlenUnicode($sub) > INPUT_MAX)     error(_T('regist_topictoolong'), $dest);
    if(strlenUnicode($resto) > INPUT_MAX)   error(_T('regist_longthreadnum'), $dest);
 
    setrawcookie('namec', rawurlencode($name), time()+7*24*3600);
 
    // E-mail / Title trimming
    $email = str_replace("\r\n", '', $email); 
    $sub = str_replace("\r\n", '', $sub);
 
    applyTripcodeAndCapCodes($name, $email, $dest);
    cleanComment($com, $upfile_status, $is_admin, $dest);
	addDefaultText($sub, $com);
    applyPostFilters($preview, $com, $email);
 
    // Trimming label style
    if($category && USE_CATEGORY){
        $category = explode(',', $category); // Disassemble the labels into an array
        $category = ','.implode(',', array_map('trim', $category)).','; // Remove the white space and merge into a single string (left and right, you can directly search in the form XX)
    }else{ 
        $category = ''; 
    }
    
    if($up_incomplete){
        $com .= '<br /><br /><span class="warning">'._T('notice_incompletefile').'</span>'; // Tips for uploading incomplete additional image files
    }
 
    // Password and time style
    if($pwd==''){
        $pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
    }
 
    $pass = $pwd ? substr(md5($pwd), 2, 8) : '*'; // Generate a password for true storage judgment (the 8 characters at the bottom right of the imageboard where it says Password ******** SUBMIT for deleting posts)
    $now = generatePostDay($time);
    $now .= generatePostID($email,$now, $time, $resto, $PIO);
 
    validateForDatabase($pwdc, $com, $time, $pass, $host,  $upfile, $md5chksum, $dest, $PIO);
    if($resto){
        $ThreadExistsBefore = $PIO->isThread($resto);
    }
 
    pruneOld($PMS, $PIO, $FileIO, $delta_totalsize);
    threadSanityCheck($chktime, $flgh, $resto, $PIO, $dest, $ThreadExistsBefore);
 
    // Calculate the last feilds needed before putitng in db
    $no = $PIO->getLastPostNo('beforeCommit') + 1;
    if(!isset($ext)) $ext = '';
    if(!isset($imgW)) $imgW = 0;
    if(!isset($imgH)) $imgH = 0;
    if(!isset($imgsize)) $imgsize = '';
    if(!isset($W)) $W = 0;
    if(!isset($H)) $H = 0;
    if(!isset($md5chksum)) $md5chksum = '';
    $age = false;
    $status = '';
    applyAging($resto, $PIO, $time, $chktime, $email, $name);
 
    // noko
    $redirect = PHP_SELF2.'?'.$tim;
    if (strstr($email, 'noko') && !strstr($email, 'nonoko')) {
        $redirect = PHP_SELF.'?res='.($resto?$resto:$no);
        if (!strstr($email, 'noko2')){
            $redirect.= "#p$no";
        }
    }
    $email = preg_replace('/^(no)+ko\d*$/i', '', $email);
 
	// Get number of pages to rebuild
	$threads = $PIO->fetchThreadList();
	$threads_count = count($threads);
	$page_end = ($resto ? floor(array_search($resto, $threads) / PAGE_DEF) : ceil($threads_count / PAGE_DEF));
 
    $PMS->useModuleMethods('RegistBeforeCommit', array(&$name, &$email, &$sub, &$com, &$category, &$age, $dest, $resto, array($W, $H, $imgW, $imgH, $tim, $ext), &$status)); // "RegistBeforeCommit" Hook Point
    $PIO->addPost($no, $resto, $md5chksum, $category, $tim, $fname, $ext, $imgW, $imgH, $imgsize, $W, $H, $pass, $now, $name, $email, $sub, $com, $host, $age, $status);
    if($preview) {
        previewPost($no);
        return;
    }
     
	$level = $AccountIO->valid();
 	//username for logging
 	$moderatorUsername = $AccountIO->getUsername();
	$moderatorLevel = $AccountIO->getRoleLevel();
	
	logtime("Post No.$no registered", $moderatorUsername.' ## '.$moderatorLevel);
    // Formal writing to storage
    $PIO->dbCommit();
    $lastno = $PIO->getLastPostNo('afterCommit'); // Get this new article number
    $PMS->useModuleMethods('RegistAfterCommit', array($lastno, $resto, $name, $email, $sub, $com)); // "RegistAfterCommit" Hook Point
 
    // Cookies storage: password and e-mail part, for one week
    setcookie('pwdc', $pwd, time()+7*24*3600);
    setcookie('emailc', $email, time()+7*24*3600);
    makeThumbnailAndUpdateStats($delta_totalsize, $dest, $ext, $tim, $tmpfile ,$imgW, $imgH, $W, $H);
    runWebhooks($resto,  $no,  $sub);
 
 
    // delta != 0 indicates that the total file size has changed and the cache must be updated
    if($delta_totalsize != 0){
        $FileIO->updateStorageSize($delta_totalsize);
    }
	updatelog(0, -1, false, $page_end);
 
    if(isset($_POST['up_series'])){
        if($resto) $redirect = PHP_SELF.'?res='.$resto.'&upseries=1';
        else $redirect = PHP_SELF.'?res='.$lastno.'&upseries=1';
    }
 	
    redirect($redirect, 0);
}
/* User post deletion */
function usrdel(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$AccountIO = PMCLibrary::getAccountIOInstance();
	
	// $pwd: User input value, $pwdc: Cookie records password
	$pwd = $_POST['pwd']??'';
	$pwdc = $_COOKIE['pwdc']??'';
	$onlyimgdel = $_POST['onlyimgdel']??'';
	$delno = array();
	reset($_POST);
	foreach($_POST as $key=>$val){
		if (!is_numeric($key)) {
			continue;
		}
		if($val==='delete'){
			array_push($delno,$key);
			$delflag=TRUE;
		}
	}
	$haveperm = $AccountIO->valid()>=LEV_JANITOR;
	$PMS->useModuleMethods('Authenticate', array($pwd,'userdel',&$haveperm));
	if($haveperm && isset($_POST['func'])){ // If the user has permissions (admin, mod, or janny) for front-end management capabilities
		$message = '';
		$PMS->useModuleMethods('AdminFunction', array('run', &$delno, $_POST['func'], &$message)); // "AdminFunction" Hook Point
		if($_POST['func'] != 'delete'){
			if(isset($_SERVER['HTTP_REFERER'])){
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: '.$_SERVER['HTTP_REFERER']);
			}
			exit(); // Only execute AdminFunction to terminate the deletion action
		}
	}

	if($pwd=='' && $pwdc!='') $pwd = $pwdc;
	$pwd_md5 = substr(md5($pwd),2,8);
	$host = gethostbyaddr(getREMOTE_ADDR());
	$search_flag = $delflag = false;

	if(!count($delno)) error(_T('del_notchecked'));
	
    $level = $AccountIO->valid();
 	//username for logging
	$moderatorUsername = $AccountIO->getUsername();
	$moderatorLevel = $AccountIO->getRoleLevel();
	
	$delposts = array(); // Articles that are truly eligible for deletion
	$posts = $PIO->fetchPosts($delno);
	foreach($posts as $post){
		if($pwd_md5==$post['pwd'] || $host==$post['host'] || $haveperm){
			$search_flag = true; // Found
			array_push($delposts, intval($post['no']));
			logtime("Delete post No.".$post['no'].($onlyimgdel?' (file only)':''), $moderatorUsername.' ## '.$moderatorLevel);
		}
	}
	if($search_flag){
		if(!$onlyimgdel) $PMS->useModuleMethods('PostOnDeletion', array($delposts, 'frontend')); // "PostOnDeletion" Hook Point
		$files = $onlyimgdel ? $PIO->removeAttachments($delposts) : $PIO->removePosts($delposts);
		$FileIO->updateStorageSize(-$FileIO->deleteImage($files)); // Update capacity cache
		deleteCache($delposts);
		$PIO->dbCommit();
	}else error(_T('del_wrongpwornotfound'));
	updatelog();
	if(isset($_POST['func']) && $_POST['func'] == 'delete'){ // Front-end management deletes the article and returns to the management page
		if(isset($_SERVER['HTTP_REFERER'])){
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.$_SERVER['HTTP_REFERER']);
		}
		exit();
	}
}

/* Displays loaded module information */
function listModules(){
	$PMS = PMCLibrary::getPMSInstance();
	$AccountIO = PMCLibrary::getAccountIOInstance();
	
	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>]';
	$level = $AccountIO->getRoleLevel();
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

/*-----------The main judgment of the functions of the program-------------*/
if(GZIP_COMPRESS_LEVEL && ($Encoding = CheckSupportGZip())){ ob_start(); ob_implicit_flush(0); } // Support and enable Gzip compression to set buffers
$mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_POST['mode']) ? $_POST['mode'] : ''); // Current operating mode (GET, POST)

switch($mode){
	case 'regist':
		regist();
		break;
	case 'preview':
		if(!USE_PREVIEW) error('ERROR: Posting preview is not enabled on this board.');
		regist(true);
		break;
	case 'admin':
		drawAdminList(); //show forms and buttons
		break;
	case 'status':
		showstatus();
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
	case 'viewAcc':
		viewAccounts();
		break;
	case 'createAcc':
		createAccount();
		break;
	case 'usrdel':
		usrdel();
	case 'rebuild':
		$AccountIO = PMCLibrary::getAccountIOInstance();
		$login = $AccountIO->valid();
 		//username for logging
		$moderatorUsername = $AccountIO->getUsername();
		$moderatorLevel = $AccountIO->getRoleLevel();
		if ($AccountIO->valid()>=LEV_JANITOR) {
			logtime("Rebuilt pages", $moderatorUsername.' ## '.$moderatorLevel);
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

		$res = isset($_GET['res']) ? $_GET['res'] : 0; // To respond to the number
		if($res){ // Response mode output
			$page = $_GET['pagenum']??'RE_PAGE_MAX';
			if(!($page=='all' || $page=='RE_PAGE_MAX')) $page = intval($_GET['pagenum']);
			updatelog($res, $page); // Implement pagin
		}elseif(isset($_GET['pagenum']) && intval($_GET['pagenum']) > -1){ // PHP dynamically outputs one page
			updatelog(0, intval($_GET['pagenum']));
		}else{ // Go to the static inventory page
			if(!is_file(PHP_SELF2)) {
				logtime("Rebuilt pages");
				updatelog();
			}
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: '.fullURL().PHP_SELF2.'?'.$_SERVER['REQUEST_TIME']);
		}
}

if(GZIP_COMPRESS_LEVEL && $Encoding){ // If Gzip is enabled
	if(!ob_get_length()) exit; // No content, no need to compress
	header('Content-Encoding: '.$Encoding);
	header('X-Content-Encoding-Level: '.GZIP_COMPRESS_LEVEL);
	header('Vary: Accept-Encoding');
	print gzencode(ob_get_clean(), GZIP_COMPRESS_LEVEL); // Compressed content
}

clearstatcache();
