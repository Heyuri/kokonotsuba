<?php
//template convenience library
function bindReplyValuesToTemplate($no, $resto, $sub, $name, $now, $category, $QUOTEBTN, $IMG_BAR, $imgsrc, $WARN_BEKILL, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno) {
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
	 '{$SELF}'=>PHP_SELF, '{$COM}'=>$com, 
	 '{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA,
	 '{$THREADNAV}'=>$THREADNAV, 
	 '{$BACKLINKS}'=>$BACKLINKS, 
	 '{$IS_THREAD}'=>!!$resno);
}

function bindOPValuesToTemplate($no, $sub, $name, $now, $category, $QUOTEBTN, $REPLYBTN, $IMG_BAR, $imgsrc, $fname, $fsize, $imgw, $imgh, $filelink, $numberOfReplies, $WARN_OLD, $WARN_BEKILL, $WARN_ENDREPLY, $WARN_HIDEPOST, $com, $POSTFORM_EXTRA, $THREADNAV, $BACKLINKS, $resno) {
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
	'{$SELF}'=>PHP_SELF, 
	'{$COM}'=>$com, 
	'{$POSTINFO_EXTRA}'=>$POSTFORM_EXTRA, 
	'{$THREADNAV}'=>$THREADNAV, 
	'{$BACKLINKS}'=>$BACKLINKS, 
	'{$IS_THREAD}'=>!!$resno);
}
