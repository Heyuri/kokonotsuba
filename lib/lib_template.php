<?php
//template convenience library
function bindReplyValuesToTemplate($no, $resto, $sub, $name, $now, $category, $QUOTEBTN, $IMG_BAR, $imgsrc, $WARN_BEKILL, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno) {
	global $config;
	return array('{$NO}'=>$no,
	 '{$RESTO}'=>$resto, 
	 '{$SUB}'=>$sub, 
	 '{$NAME}'=>$name, 
	 '{$NOW}'=>$now, 
	 '{$CATEGORY}'=>$category, 
	 '{$QUOTEBTN}'=>$QUOTEBTN, 
	 '{$IMG_BAR}'=>$IMG_BAR,
	 '{$IMG_SRC}'=>$imgsrc,
	 '{$WARN_BEKILL}'=>$WARN_BEKILL, 
	 '{$NAME_TEXT}'=>_T('post_name'), 
	 '{$CATEGORY_TEXT}'=>_T('post_category'), 
	 '{$SELF}'=>$config['PHP_SELF'], '{$COM}'=>$com, 
	 '{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA,
	 '{$THREADNAV}'=>$THREADNAV, 
	 '{$BACKLINKS}'=>$BACKLINKS, 
	 '{$IS_THREAD}'=>!!$resno);
}

function bindOPValuesToTemplate($no, $sub, $name, $now, $category, $QUOTEBTN, $REPLYBTN, $IMG_BAR, $imgsrc, $fname, $fsize, $imgw, $imgh, $filelink, $numberOfReplies, $WARN_OLD, $WARN_BEKILL, $WARN_ENDREPLY, $WARN_HIDEPOST, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno) {
	global $config;
	return array(
	'{$NO}'=>$no, 
	'{$RESTO}'=>$no, 
	'{$SUB}'=>$sub, 
	'{$NAME}'=>$name, 
	'{$NOW}'=>$now, 
	'{$CATEGORY}'=>$category, 
	'{$QUOTEBTN}'=>$QUOTEBTN, 
	'{$REPLYBTN}'=>$REPLYBTN, 
	'{$IMG_BAR}'=>$IMG_BAR, 
	'{$IMG_SRC}'=>$imgsrc, 
	'{$FILE_NAME}' => $fname,
	'{$ESCAPED_FILE_NAME}' => htmlspecialchars($fname),
	'{$FILE_SIZE}' => $fsize,
	'{$FILE_WIDTH}' => $imgw,
	'{$FILE_HEIGHT}' => $imgh,
	'{$FILE_LINK}' => $filelink,
	'{$REPLYNUM}' => $numberOfReplies - 1,
	'{$WARN_OLD}'=>$WARN_OLD, 
	'{$WARN_BEKILL}'=>$WARN_BEKILL,
	'{$WARN_ENDREPLY}'=>$WARN_ENDREPLY, 
	'{$WARN_HIDEPOST}'=>$WARN_HIDEPOST, 
	'{$NAME_TEXT}'=>_T('post_name'), 
	'{$CATEGORY_TEXT}'=>_T('post_category'), 
	'{$SELF}'=>$config['PHP_SELF'], 
	'{$COM}'=>$com, 
	'{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA, 
	'{$THREADNAV}'=>$THREADNAV, 
	'{$BACKLINKS}'=>$BACKLINKS, 
	'{$IS_THREAD}'=>!!$resno);
}

/* 輸出表頭 | document head */
function head(&$dat,$resno=0){
	global $config;
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();
	$PIO = PMCLibrary::getPIOInstance();

	$pte_vals = array('{$RESTO}'=>$resno?$resno:'', '{$IS_THREAD}'=>boolval($resno));
	if ($resno) {
		$post = $PIO->fetchPosts($resno);
		if (mb_strlen($post[0]['com']) <= 10){
			$CommentTitle = $post[0]['com'];
		} else {
			$CommentTitle = mb_substr($post[0]['com'],0,10,'UTF-8') . "...";
		}
		$pte_vals['{$PAGE_TITLE}'] = ($post[0]['sub'] ? $post[0]['sub'] : $CommentTitle).' - '.$config['TITLE'];
	}
	$dat .= $PTE->ParseBlock('HEADER',$pte_vals);
	$PMS->useModuleMethods('Head', array(&$dat,$resno)); // "Head" Hook Point
	$dat .= '</head>';
	$pte_vals += array('{$HOME}' => '[<a href="'.$config['HOME'].'" target="_top">'._T('head_home').'</a>]',
		'{$STATUS}' => '[<a href="'.$config['PHP_SELF'].'?mode=status">'._T('head_info').'</a>]',
		'{$ADMIN}' => '[<a href="'.$config['PHP_SELF'].'?mode=admin">'._T('head_admin').'</a>]',
		'{$REFRESH}' => '[<a href="'.$config['PHP_SELF2'].'?">'._T('head_refresh').'</a>]',
		'{$SEARCH}' => (0) ? '[<a href="'.$config['PHP_SELF'].'?mode=search">'._T('head_search').'</a>]' : '',
		'{$HOOKLINKS}' => '');
		
	$PMS->useModuleMethods('Toplink', array(&$pte_vals['{$HOOKLINKS}'],$resno)); // "Toplink" Hook Point
	$PMS->useModuleMethods('AboveTitle', array(&$pte_vals['{$BANNER}'])); //"AboveTitle" Hook Point
	
	$dat .= $PTE->ParseBlock('BODYHEAD',$pte_vals);
}

/* 輸出頁尾文字 | footer credits */
function foot(&$dat,$res=false){
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$pte_vals = array('{$FOOTER}'=>'','{$IS_THREAD}'=>$res);
	$PMS->useModuleMethods('Foot', array(&$pte_vals['{$FOOTER}'])); // "Foot" Hook Point
	$pte_vals['{$FOOTER}'] .= '<center>- <a rel="nofollow noreferrer license" href="https://web.archive.org/web/20150701123900/http://php.s3.to/" target="_blank">GazouBBS</a> + <a rel="nofollow noreferrer license" href="http://www.2chan.net/" target="_blank">futaba</a> + <a rel="nofollow noreferrer license" href="https://pixmicat.github.io/" target="_blank">Pixmicat!</a> + <a rel="nofollow noreferrer license" href="https://github.com/Heyuri/kokonotsuba/" target="_blank">Kokonotsuba</a> -</center>';
	$dat .= $PTE->ParseBlock('FOOTER',$pte_vals);
}

/* 發表用表單輸出 | user contribution form */
function form(&$dat, $resno, $name='', $mail='', $sub='', $com='', $cat='', $preview=false){
	global $config;
	$PTE = PMCLibrary::getPTEInstance();
	$PMS = PMCLibrary::getPMSInstance();

	$hidinput =
		($resno ? '<input type="hidden" name="resto" value="'.$resno.'">' : '').
		($config['TEXTBOARD_ONLY'] ? '' : '<input type="hidden" name="MAX_FILE_SIZE" value="{$MAX_FILE_SIZE}">');

	$pte_vals = array(
		'{$RESTO}' => strval($resno),
		'{$GLOBAL_MESSAGE}' => '',
		'{$BLOTTER}' => '',
		'{$IS_THREAD}' => $resno!=0,
		'{$FORM_HIDDEN}' => $hidinput,
		'{$MAX_FILE_SIZE}' => strval($config['TEXTBOARD_ONLY'] ? 0 : $config['MAX_KB'] * 1024),
		'{$FORM_NAME_FIELD}' => '<input tabindex="1" maxlength="'.$config['INPUT_MAX'].'" type="text" name="name" id="name" size="28" value="'.$name.'" class="inputtext">',
		'{$FORM_EMAIL_FIELD}' => '<input tabindex="2" maxlength="'.$config['INPUT_MAX'].'" type="text" name="email" id="email" size="28" value="'.$mail.'" class="inputtext">',
		'{$FORM_TOPIC_FIELD}' => '<input tabindex="3" maxlength="'.$config['INPUT_MAX'].'"  type="text" name="sub" id="sub" size="28" value="'.$sub.'" class="inputtext">',
		'{$FORM_SUBMIT}' => '<button tabindex="10" type="submit" name="mode" value="regist">'.($resno ? 'Post' : 'New Thread' ).'</button>',
		'{$FORM_COMMENT_FIELD}' => '<textarea tabindex="6" maxlength="'.$config['COMM_MAX'].'" name="com" id="com" cols="48" rows="4" class="inputtext">'.$com.'</textarea>',
		'{$FORM_DELETE_PASSWORD_FIELD}' => '<input tabindex="6" type="password" name="pwd" id="pwd" size="8" maxlength="8" value="" class="inputtext">',
		'{$FORM_EXTRA_COLUMN}' => '',
		'{$FORM_FILE_EXTRA_FIELD}' => '',
		'{$FORM_NOTICE}' => ($config['TEXTBOARD_ONLY'] ? '' :_T('form_notice',str_replace('|',', ',$config['ALLOW_UPLOAD_EXT']),$config['MAX_KB'],($resno ? $config['MAX_RW'] : $config['MAX_W']),($resno ? $config['MAX_RH'] : $config['MAX_H']))),
		'{$HOOKPOSTINFO}' => '');
	if(!$config['TEXTBOARD_ONLY'] && ($config['RESIMG'] || !$resno)){
		if(isset($_FILES['upfile']['error']) && $_FILES['upfile']['error']!=UPLOAD_ERR_NO_FILE) $w = ($preview?'<small class="warning"><b>Please enter the file again:</b></small><br>':'');
		else $w = '';
		$pte_vals += array('{$FORM_ATTECHMENT_FIELD}' => $w.'<input type="file" name="upfile" id="upfile">');

		if (!$resno) {
			$pte_vals += array('{$FORM_NOATTECHMENT_FIELD}' => '<input type="checkbox" name="noimg" id="noimg" value="on">');
		}
		if($config['USE_UPSERIES']) { // 啟動連貼機能
			$pte_vals['{$FORM_CONTPOST_FIELD}'] = '<input type="checkbox" name="up_series" id="up_series" value="on"'.((isset($_GET["upseries"]) && $resno)?' checked="checked"':'').'>';
		}
		$PMS->useModuleMethods('PostFormFile', array(&$pte_vals['{$FORM_FILE_EXTRA_FIELD}']));
	}
	$PMS->useModuleMethods('PostForm', array(&$pte_vals['{$FORM_EXTRA_COLUMN}'])); // "PostForm" Hook Point
	if($config['USE_CATEGORY']) {
		$pte_vals += array('{$FORM_CATEGORY_FIELD}' => '<input tabindex="5" type="text" name="category" id="category" size="28" value="'.$cat.'" class="inputtext">');
	}
	if($config['STORAGE_LIMIT']) $pte_vals['{$FORM_NOTICE_STORAGE_LIMIT}'] = _T('form_notice_storage_limit',total_size(),$config['STORAGE_MAX']);
	$PMS->useModuleMethods('PostInfo', array(&$pte_vals['{$HOOKPOSTINFO}'])); // "PostInfo" Hook Point
	
	$PMS->useModuleMethods('GlobalMessage', array(&$pte_vals['{$GLOBAL_MESSAGE}'])); // "GlobalMessage" Hook Point
	$PMS->useModuleMethods('BlotterPreview', array(&$pte_vals['{$BLOTTER}'])); // "Blotter Preview" Hook Point
	
	$dat .= $PTE->ParseBlock('POSTFORM',$pte_vals);
}
