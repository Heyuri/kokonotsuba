<?php

define("PIXMICAT_VER", 'Koko BBS Release 1'); // Version information text
/*

YOU MUST GIVE CREDIT TO WWW.HEYURI.NET ON YOUR BBS IF YOU ARE PLANNING TO USE THIS SOFTWARE.

*/
if (file_exists('.lockdown') && valid() < LEV_JANITOR) {
	die('Posting temporarily disabled. Come back later!');
}

@session_start();

require './config.php'; // Introduce a settings file
require ROOTPATH.'lib/pmclibrary.php'; // Ingest libraries
require ROOTPATH.'lib/lib_errorhandler.php'; // Introduce global error capture
require ROOTPATH.'lib/lib_compatible.php'; // Introduce compatible libraries
require ROOTPATH.'lib/lib_common.php'; // Introduce common function archives

defined("ROLL") or define("ROLL",[]);//When undefined, empty array

/* Update the log file/output thread */
function updatelog($resno=0,$pagenum=-1,$single_page=false){
	global $LIMIT_SENSOR;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$pagenum = intval($pagenum);

	$adminMode = valid()>=LEV_JANITOR && $pagenum != -1 && !$single_page; // Front-end management mode

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
			$page_end = ceil($threads_count / PAGE_DEF); // The last value of the page number
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
		if(!$PIO->isThread($resno)){ error(_T('thread_not_found')); }
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
	// Obsolete
	/*if(PIOSensor::check('predict', $LIMIT_SENSOR)){ // Whether a forecast is required
		$old_sensor = true; // tag opens
		$arr_old = array_flip(PIOSensor::listee('predict', $LIMIT_SENSOR)); // Array of old articles
	}*/
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
			if(STATIC_HTML_UNTIL != -1 && STATIC_HTML_UNTIL==$page) break; // Page Limit
		}else{ // PHP output (responsive mode/regular dynamic output)
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

		if($adminMode){ // Front-end management mode
			$modFunc = '';
			$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$i], $resto)); // "AdminList" Hook Point
			$POSTFORM_EXTRA .= $modFunc;
		}

		// Set thread properties
		if(STORAGE_LIMIT && $kill_sensor) if(isset($arr_kill[$no])) $WARN_BEKILL = '<span class="warning">'._T('warn_sizelimit').'</span><br />'; // Predict to delete too large files
		if(!$i){ // 首篇 Only
			//if($old_sensor) if(isset($arr_old[$no])) $WARN_OLD = '<span class="warning">'._T('warn_oldthread').'</span><br />'; // Reminder that it is about to be deleted (Obsolete)
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
			$arrLabels = array('{$NO}'=>$no, '{$RESTO}'=>$resto, '{$SUB}'=>$sub, '{$NAME}'=>$name, '{$NOW}'=>$now, '{$CATEGORY}'=>$category, '{$QUOTEBTN}'=>$QUOTEBTN, '{$IMG_BAR}'=>$IMG_BAR, '{$IMG_SRC}'=>$imgsrc, '{$WARN_BEKILL}'=>$WARN_BEKILL, '{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category'), '{$SELF}'=>PHP_SELF, '{$COM}'=>$com, '{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA, '{$THREADNAV}'=>$THREADNAV, '{$BACKLINKS}'=>$BACKLINKS, '{$IS_THREAD}'=>!!$resno);
			if($resno) $arrLabels['{$RESTO}']=$resno;
			$PMS->useModuleMethods('ThreadReply', array(&$arrLabels, $posts[$i], $resno)); // "ThreadReply" Hook Point
			$thdat .= $PTE->ParseBlock('REPLY',$arrLabels);
		}else{ // First Article
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

/* Write to log file */
function regist($preview=false){
	global $BAD_STRING, $BAD_FILEMD5, $BAD_IPADDR, $LIMIT_SENSOR;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$fname = $dest = $mes = ''; $up_incomplete = 0; $is_admin = false;
	$delta_totalsize = 0; // The change in the total file size

	if(!$_SERVER['HTTP_REFERER']
	|| !$_SERVER['HTTP_USER_AGENT']
	|| preg_match("/^(curl|wget)/i", $_SERVER['HTTP_USER_AGENT']) ){
		error('You look like a robot.', $dest);
	}

	if($_SERVER['REQUEST_METHOD'] != 'POST') error(_T('regist_notpost')); // Informal POST method

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
	// Blocking: IP/Hostname/DNSBL Check Function
	$baninfo = '';
	if(BanIPHostDNSBLCheck($ip, $host, $baninfo)) error(_T('regist_ipfiltered', $baninfo));
	// Block: Restrict the text that appears (text filter?)
	foreach($BAD_STRING as $value){
		if(preg_match($value, $com) || preg_match($value, $sub) || preg_match($value, $name) || preg_match($value, $email)){
			error(_T('regist_wordfiltered'));
		}
	}

	// Check if you enter Sakura Japanese kana (kana = Japanese syllabary)
	foreach(array($name, $email, $sub, $com) as $anti) if(anti_sakura($anti)) error(_T('regist_sakuradetected'));

	// Time
	$time = $_SERVER['REQUEST_TIME'];
	$tim = $time.substr($_SERVER['REQUEST_TIME_FLOAT'],2,3);

	if(!TEXTBOARD_ONLY) {
		$upfile = CleanStr($_FILES['upfile']['tmp_name']??'');
		$upfile_path = $_POST['upfile_path']??'';
		$upfile_name = $_FILES['upfile']['name']??'';
		$upfile_status = $_FILES['upfile']['error']??UPLOAD_ERR_NO_FILE;

		// Determine the upload status
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

		// If there is an uploaded file, process the additional image file
		if($upfile && (@is_uploaded_file($upfile) || @is_file($upfile)) && !$preview){
			// 1. Save the file first
			$dest = STORAGE_PATH .$tim.'.tmp';
			@move_uploaded_file($upfile, $dest) or @copy($upfile, $dest);
			@chmod($dest, 0666);
			if(!is_file($dest)) error(_T('regist_upload_filenotfound'), $dest);
           // Remove exif
    if (function_exists('exif_read_data') && function_exists('exif_imagetype')) {
        $imageType = exif_imagetype($dest);

        if ($imageType == IMAGETYPE_JPEG) {
            $exif = @exif_read_data($dest);
            if ($exif !== false) {
                // Remove Exif data
                $image = imagecreatefromjpeg($dest);
                imagejpeg($image, $dest, 100);
                imagedestroy($image);
            }
        }
    }

			// 2. Determine whether there is any interruption in the process of uploading additional image files
			$upsizeTTL = $_SERVER['CONTENT_LENGTH'];
			if(isset($_FILES['upfile'])){ // Only when there is transmitted data does it need to be calculated, so as to avoid white work
				$upsizeHDR = 0;
				// File path: IE has the full path attached, so you have to get it from the hidden form
				$tmp_upfile_path = $upfile_name;
				if($upfile_path) $tmp_upfile_path = $upfile_path;
				list(,$boundary) = explode('=', $_SERVER['CONTENT_TYPE']);
				foreach($_POST as $header => $value){ // Form fields transfer data
					$upsizeHDR += strlen('--'.$boundary."\r\n")
					+ strlen('Content-Disposition: form-data; name="'.$header.'"'."\r\n\r\n".($value)."\r\n");
				}
				// The attached image file field transmits the data
				$upsizeHDR += strlen('--'.$boundary."\r\n")
				+ strlen('Content-Disposition: form-data; name="upfile"; filename="'.$tmp_upfile_path."\"\r\n".'Content-Type: '.$_FILES['upfile']['type']."\r\n\r\n")
				+ strlen("\r\n--".$boundary."--\r\n")
				+ $_FILES['upfile']['size']; // Send attachment data
				// The upload byte difference exceeds HTTP_UPLOAD_DIFF: The upload of additional image files is incomplete
				if(($upsizeTTL - $upsizeHDR) > HTTP_UPLOAD_DIFF){
					if(KILL_INCOMPLETE_UPLOAD){
						unlink($dest);
						die(_T('regist_upload_killincomp')); // The prompt to the browser, if the user still sees it, will not be puzzled
					}else $up_incomplete = 1;
				}
			}

			// 3. Check whether it is an acceptable file
			$size = @getimagesize($dest);
			$imgsize = @filesize($dest); // File size
			if ($imgsize > MAX_KB*1024) error(_T('regist_upload_exceedcustom'));
			$imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // Discrimination of KB and B
			$fname = Cleanstr(pathinfo($upfile_name, PATHINFO_FILENAME));
			$ext = '.'.strtolower(pathinfo($upfile_name, PATHINFO_EXTENSION));
			if (is_array($size)) {
				// File extension detection from Heyuri
				// Don't assume the script supports the file type just because the extension is here.
				switch($size[2]){ // Determine the format of the uploaded image file
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
					$imgsize = @filesize($dest); // File size
					$imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // Discrimination of KB and B
				}
			}
			$allow_exts = explode('|', strtolower(ALLOW_UPLOAD_EXT)); // Accepted additional image file extension
			if(array_search(substr($ext, 1), $allow_exts)===false) error(_T('regist_upload_notsupport'), $dest); // Uploaded file not allowed due to wrong file extension
			// Block setting: Restrict the upload of MD5 checkcodes for additional images
			$md5chksum = md5_file($dest); // File MD%
			if(array_search($md5chksum, $BAD_FILEMD5)!==false) error(_T('regist_upload_blocked'), $dest); // If the MD5 checkcode of the uploaded file is in the block list, the upload is blocked

			// 4. Calculate the thumbnail display size of the additional image file
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

	// Check the form field contents and trim them
	if(strlenUnicode($name) > INPUT_MAX) error(_T('regist_nametoolong'), $dest);
	if(strlenUnicode($email) > INPUT_MAX) error(_T('regist_emailtoolong'), $dest);
	if(strlenUnicode($sub) > INPUT_MAX) error(_T('regist_topictoolong'), $dest);
	if(strlenUnicode($resto) > INPUT_MAX) error(_T('regist_longthreadnum'), $dest);

	setrawcookie('namec', rawurlencode($name), time()+7*24*3600);
	// E-mail / Title trimming
	$email = str_replace("\r\n", '', $email); $sub = str_replace("\r\n", '', $sub);
	// Tripcode crap
	$name = str_replace('&#', '&&', $name); // otherwise HTML numeric entities will explode!
	list($name, $trip, $sectrip) = str_replace('&%', '&#', explode('#',$name.'##'));
	if ($trip) {
		$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
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
	
	if(stristr($email, 'vipcode') && defined('VIPDEF')) {
			$name .= ' <img src="'.STATIC_URL.'vip.gif" title="This user is a VIP user" style="vertical-align: middle;margin-top: -2px;" alt="VIP">'; 
	}
	$email = preg_replace('/^vipcode$/i', '', $email);
	
	// Text trimming
	if((strlenUnicode($com) > COMM_MAX) && !$is_admin) error(_T('regist_commenttoolong'), $dest);
	$com = CleanStr($com, $is_admin); // The$ is_admin parameter is introduced because when the administrator starts, the administrator is allowed to set whether to use HTML according to config.
	if(!$com && $upfile_status==4) error(TEXTBOARD_ONLY?'ERROR: No text entered.':_T('regist_withoutcomment'));
	$com = str_replace(array("\r\n", "\r"), "\n", $com); $com = preg_replace("/\n((　| )*\n){3,}/", "\n", $com);
	if(!BR_CHECK || substr_count($com,"\n") < BR_CHECK) $com = nl2br($com); // Newline characters are replaced by <br />
	$com = str_replace("\n", '', $com); // If there are still \n newline characters, cancel the newline
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
	// Default content
	if(!$sub || preg_match("/^[ |　|]*$/", $sub)) $sub = DEFAULT_NOTITLE;
	if(!$com || preg_match("/^[ |　|\t]*$/", $com)) $com = DEFAULT_NOCOMMENT;
	// Trimming label style
	if($category && USE_CATEGORY){
		$category = explode(',', $category); // Disassemble the labels into an array
		$category = ','.implode(',', array_map('trim', $category)).','; // Remove the white space and merge into a single string (left and right, you can directly search in the form XX)
	}else{ $category = ''; }
	if($up_incomplete) $com .= '<br /><br /><span class="warning">'._T('notice_incompletefile').'</span>'; // Tips for uploading incomplete additional image files

	// Password and time style
	if($pwd=='') $pwd = ($pwdc=='') ? substr(rand(),0,8) : $pwdc;
	$pass = $pwd ? substr(md5($pwd), 2, 8) : '*'; // Generate a password for true storage judgment (the 8 characters at the bottom right of the imageboard where it says Password ******** SUBMIT for deleting posts)
	$youbi = array(_T('sun'),_T('mon'),_T('tue'),_T('wed'),_T('thu'),_T('fri'),_T('sat'));
	$yd = $youbi[gmdate('w', $time+TIME_ZONE*60*60)];
	$now = gmdate('Y/m/d', $time+TIME_ZONE*60*60).'('.(string)$yd.')'.gmdate('H:i:s', $time+TIME_ZONE*60*60);
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

	// Continuous submission / same additional image check
	$checkcount = 50; // Check 50 by default
	$pwdc = substr(md5($pwdc), 2, 8); // Cookies Password
	if (valid()<LEV_MODERATOR or defined('VIPDEF'))  {
		if($PIO->isSuccessivePost($checkcount, $com, $time, $pass, $pwdc, $host, $upfile_name))
			error(_T('regist_successivepost'), $dest); // Continuous submission check
		if($dest){ if($PIO->isDuplicateAttachment($checkcount, $md5chksum)) error(_T('regist_duplicatefile'), $dest); } // Same additional image file check
	}
	if($resto) $ThreadExistsBefore = $PIO->isThread($resto);

	// Deletion of old articles
	if(PIOSensor::check('delete', $LIMIT_SENSOR)){
		$delarr = PIOSensor::listee('delete', $LIMIT_SENSOR);
		if(count($delarr)){
			deleteCache($delarr);
			$PMS->useModuleMethods('PostOnDeletion', array($delarr, 'recycle')); // "PostOnDeletion" Hook Point
			$files = $PIO->removePosts($delarr);
			if(count($files)) $delta_totalsize -= $FileIO->deleteImage($files); // Update delta value
		}
	}

	// Additional image file capacity limit function is enabled: delete oversized files
	if(STORAGE_LIMIT && STORAGE_MAX > 0){
		$tmp_total_size = $FileIO->getCurrentStorageSize(); // Get the current size of additional images
		if($tmp_total_size > STORAGE_MAX){
			$files = $PIO->delOldAttachments($tmp_total_size, STORAGE_MAX, false);
			$delta_totalsize -= $FileIO->deleteImage($files);
		}
	}

	// Determine whether the article you want to respond to has just been deleted
	if($resto){
		if($ThreadExistsBefore){ // If the thread of the discussion you want to reply to exists
			if(!$PIO->isThread($resto)){ // If the thread of the discussion you want to reply to has been deleted
				// Update the data source in advance, and this new addition is not recorded
				$PIO->dbCommit();
				updatelog();
				error(_T('regist_threaddeleted'), $dest);
			}else{ // Check that the thread is set to suppress response (by the way, take out the post time of the original post)
				$post = $PIO->fetchPosts($resto); // [Special] Take a single article content, but the $post of the return also relies on [$i] to switch articles!
				list($chkstatus, $chktime) = array($post[0]['status'], $post[0]['tim']);
				$chktime = substr($chktime, 0, -3); // Remove microseconds (the last three characters)
				$flgh = $PIO->getPostStatus($chkstatus);
			}
		}else error(_T('thread_not_found'), $dest); // Does not exist
	}

	// Calculate field values
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
			if(!MAX_AGE_TIME || (($time - $chktime) < (MAX_AGE_TIME * 60 * 60))) $age = true; // Discussion threads are not expired
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
	// Formal writing to storage
	$PIO->dbCommit();
	$lastno = $PIO->getLastPostNo('afterCommit'); // Get this new article number
	$PMS->useModuleMethods('RegistAfterCommit', array($lastno, $resto, $name, $email, $sub, $com)); // "RegistAfterCommit" Hook Point

	// Cookies storage: password and e-mail part, for one week
	setcookie('pwdc', $pwd, time()+7*24*3600);
	setcookie('emailc', $email, time()+7*24*3600);
	if($dest && is_file($dest)){
		$destFile = IMG_DIR.$tim.$ext; // Image file storage location
		$thumbFile = THUMB_DIR.$tim.'s.'.THUMB_SETTING['Format']; // Preview image storage location
		if (defined(CDN_DIR)) {
			$destFile = CDN_DIR.$destFile;
			$thumbFile = CDN_DIR.$thumbFile;
		}
		if(USE_THUMB !== 0){ // Generate preview image
			$thumbType = USE_THUMB; if(USE_THUMB==1){ $thumbType = THUMB_SETTING['Method']; }
			require(ROOTPATH.'lib/thumb/thumb.'.$thumbType.'.php');
			if (isset($tmpfile)) $thObj = new ThumbWrapper($tmpfile, $imgW, $imgH);
			else $thObj = new ThumbWrapper($dest, $imgW, $imgH);
			$thObj->setThumbnailConfig($W, $H, THUMB_SETTING);
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
			$FileIO->uploadImage($tim.'s.'.THUMB_SETTING['Format'], $thumbFile, filesize($thumbFile));
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


	// delta != 0 indicates that the total file size has changed and the cache must be updated
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

/* User post deletion */
function usrdel(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

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
	$haveperm = valid()>=LEV_JANITOR;
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

	$delposts = array(); // Articles that are truly eligible for deletion
	$posts = $PIO->fetchPosts($delno);
	foreach($posts as $post){
		if($pwd_md5==$post['pwd'] || $host==$post['host'] || $haveperm){
			$search_flag = true; // Found
			array_push($delposts, intval($post['no']));
			logtime("Delete post No.".$post['no'].($onlyimgdel?' (file only)':''), valid());
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

/* Manage article(threads) mode */
function admindel(&$dat){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$pass = $_POST['pass']??''; // Admin password
	$page = $_REQUEST['page']??0; // Toggle the number of pages
	$onlyimgdel = $_POST['onlyimgdel']??''; // Only delete the image
	$modFunc = '';
	$delno = $thsno = array();
	$message = ''; // Display message after deletion
	preg_match("/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/", $_GET['host'], $hostMatch);
	$searchHost = $hostMatch[0];
	if ($searchHost) {
		if (valid() <= LEV_JANITOR) error('ERROR: No Access.');
		$noticeHost = '<h2>Viewing all posts from: '.$searchHost.'. Click submit to cancel.</h2><br />';
	}
	

	// Delete the article(thread) block
	$delno = array_merge($delno, $_POST['clist']??array());
	if($delno) logtime("Delete post No.$delno".($onlyimgdel?' (file only)':''), valid());
	if($onlyimgdel != 'on') $PMS->useModuleMethods('PostOnDeletion', array($delno, 'backend')); // "PostOnDeletion" Hook Point
	$files = ($onlyimgdel != 'on') ? $PIO->removePosts($delno) : $PIO->removeAttachments($delno);
	$FileIO->updateStorageSize(-$FileIO->deleteImage($files));
	deleteCache($delno);
	$PIO->dbCommit();

	$line = ($searchHost ? $PIO->fetchPostList(0, 0, 0, $searchHost) : $PIO->fetchPostList(0, $page * ADMIN_PAGE_DEF, ADMIN_PAGE_DEF)); // A list of tagged articles
	$posts_count = count($line); // Number of cycles
	$posts = $PIO->fetchPosts($line); // Article content array

	$dat.= '<form action="'.PHP_SELF.'" method="POST">';
	$dat.= '<input type="hidden" name="mode" value="admin" />
<input type="hidden" name="admin" value="del" />
<div align="left">'._T('admin_notices').'</div>'.
$message.'<br />'.$noticeHost.'
<center><table width="95%" cellspacing="0" cellpadding="0" border="1" class="postlists">
<thead><tr>'._T('admin_list_header').'</tr></thead>
<tbody>';

	for($j = 0; $j < $posts_count; $j++){
		$bg = ($j % 2) ? 'row1' : 'row2'; // Background color
		extract($posts[$j]);
		
		// Modify the field style
		//$now = preg_replace('/.{2}\/(.{5})\(.+?\)(.{5}).*/', '$1 $2', $now);
		$name = htmlspecialchars(str_cut(html_entity_decode(strip_tags($name)), 9));
		$sub = htmlspecialchars(str_cut(html_entity_decode($sub), 8));
		if($email) $name = "<a href=\"mailto:$email\">$name</a>";
		$com = str_replace('<br />',' ',$com);
		$com = htmlspecialchars(str_cut(html_entity_decode($com), 20));

		// The first part of the discussion is the stop tick box and module function
		$modFunc = ' ';
		$PMS->useModuleMethods('AdminList', array(&$modFunc, $posts[$j], $resto)); // "AdminList" Hook Point
		if($resto==0){ // $resto = 0 (the first part of the discussion string)
			$flgh = $PIO->getPostStatus($status);
		}

		// Extract additional archived image files and generate a link
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

		// Print out the interface
		$dat.= <<< _ADMINEOF_
<tr align="LEFT">
	<th align="center">$modFunc</th><th><input type="checkbox" name="clist[]" value="$no" />$no</th>
	<td><small class="time">$now</small></td>
	<td><b class="title">$sub</b></td>
	<td><b class="name">$name</b></td>
	<td><small>$com</small></td>
	<td>$host <a target="_blank" href="https://otx.alienvault.com/indicator/ip/$host" title="Resolve hostname"><img height="12" src="https://static.heyuri.net/image/glass.png"></a> <a href="?mode=admin&admin=del&host=$host" title="See all posts">★</a></td>
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

	$countline = $PIO->postCount(); // Total number of articles(threads)
	$page_max = ($searchHost ? 0 : ceil($countline / ADMIN_PAGE_DEF) - 1); // Total number of pages
	$dat.= '<table id="pager" border="1" cellspacing="0" cellpadding="0"><tbody><tr>';
	if($page) $dat.= '<td><a href="'.PHP_SELF.'?mode=admin&admin=del&page='.($page - 1).($searchHost?'&host='.$searchHost:'').'">'._T('prev_page').'</a></td>';
	else $dat.= '<td nowrap="nowrap">'._T('first_page').'</td>';
	$dat.= '<td>';
	for($i = 0; $i <= $page_max; $i++){
		if($i==$page) $dat.= '[<b>'.$i.'</b>] ';
		else $dat.= '[<a href="'.PHP_SELF.'?mode=admin&admin=del&page='.$i.($searchHost?'&host='.$searchHost:'').'">'.$i.'</a>] ';
	}
	$dat.= '</td>';
	if($page < $page_max) $dat.= '<td><a href="'.PHP_SELF.'?mode=admin&admin=del&page='.($page + 1).($searchHost?'&host='.$searchHost:'').'">'._T('next_page').'</a></td>';
	else $dat.= '<td nowrap="nowrap">'._T('last_page').'</td>';
	$dat.= '</tr></tbody></table>';
}

/**
 * Calculate the current capacity of additional files (unit: KB)
 * @deprecated Use FileIO->getCurrentStorageSize() / FileIO->updateStorageSize($delta) instead
 */
function total_size($delta=0){
	$FileIO = PMCLibrary::getFileIOInstance();
	return $FileIO->getCurrentStorageSize($delta);
}

/* Search (full-text search) function */
function search(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	if(!USE_SEARCH) error(_T('search_disabled'));
	$searchKeyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : ''; // The text you want to search
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
		$searchField = $_POST['field']; // Search target (no:number, name:name, sub:title, com:text)
		$searchMethod = $_POST['method']; // Search method
		$searchKeyword = preg_split('/(　| )+/', strtolower(trim($searchKeyword))); // Search text is cut with spaces
		if ($searchMethod=='REG') $searchMethod = 'AND';
		$hitPosts = $PIO->searchPost($searchKeyword, $searchField, $searchMethod); // Directly return the matching article content array

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

/* Use category tags to search for articles that match */
function searchCategory(){
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

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
	$page_max = ceil($loglist_count / PAGE_DEF); if($page > $page_max) $page = $page_max; // Total pages

	// Slice the array and get the range for pagination purposes
	$loglist_cut = array_slice($loglist, PAGE_DEF * ($page - 1), PAGE_DEF); // Take out a specific range of articles
	$loglist_cut_count = count($loglist_cut);

	$dat = '';
	head($dat);
	$links = '[<a href="'.PHP_SELF2.'?'.time().'">'._T('return').'</a>] [<a href="'.PHP_SELF.'?mode=category&c='.$category_enc.'&recache=1">'._T('category_recache').'</a>]';
	$level = valid();
	$PMS->useModuleMethods('LinksAboveBar', array(&$links,'category',$level));
	$dat .= "<div>$links</div>\n";
	for($i = 0; $i < $loglist_cut_count; $i++){
		$posts = $PIO->fetchPosts($loglist_cut[$i]); // Get article content
		$dat .= arrangeThread($PTE, ($posts[0]['resto'] ? $posts[0]['resto'] : $posts[0]['no']), null, $posts, 0, $loglist_cut[$i], array(), array(), false, false, false); // Output by output (reference links are not displayed)
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

/* Displays loaded module information */
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

/* Delete the old page cache file */
function deleteCache($no){
	foreach($no as $n){
		if($oldCaches = glob('./cache/'.$n.'-*')){
			foreach($oldCaches as $o) @unlink($o);
		}
	}
}

/* Display system information */
function showstatus(){
	global $LIMIT_SENSOR;
	$PIO = PMCLibrary::getPIOInstance();
	$FileIO = PMCLibrary::getFileIOInstance();
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$countline = $PIO->postCount(); // Calculate the current number of data entries in the submitted text log file
	$counttree = $PIO->threadCount(); // Calculate the current number of data entries in the tree structure log file
	$tmp_total_size = $FileIO->getCurrentStorageSize(); // The total size of the attached image file usage
	$tmp_ts_ratio = STORAGE_MAX > 0 ? $tmp_total_size / STORAGE_MAX : 0; // Additional image file usage

	// Determines the color of the "Additional Image File Usage" prompt
  	if($tmp_ts_ratio < 0.3 ) $clrflag_sl = '235CFF';
	elseif($tmp_ts_ratio < 0.5 ) $clrflag_sl = '0CCE0C';
	elseif($tmp_ts_ratio < 0.7 ) $clrflag_sl = 'F28612';
	elseif($tmp_ts_ratio < 0.9 ) $clrflag_sl = 'F200D3';
	else $clrflag_sl = 'F2004A';

	// Generate preview image object information and whether the functions of the generated preview image are normal
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
		<tr><td>'._T('info_basic_use_sample', THUMB_SETTING['Quality']).'</td><td colspan="3"> '.USE_THUMB.' '._T('info_0notuse1use').'</td></tr>
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