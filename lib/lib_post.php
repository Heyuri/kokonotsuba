<?php
//post lib

function applyRoll($preview, &$com, &$email){
    if (!$preview) {
        $com = "$com<br/><br/><font color='#ff0000'><b>[NUMBER: ".rand(1,10000)."]</b></font>";
        $email = preg_replace('/^roll( *)/i', '');
    } else {
        $com = "$com<br /><br /><font color=\"#F00\"><b>DON'T TRY TO CHEAT THE SYSTEM!</b></font>";
    }
}

function applyFortune($preview, &$com, &$email){
    if (!$preview) {
        $fortunenum=array_rand(FORTUNES);
        $fortcol=sprintf("%02x%02x%02x",
            127+127*sin(2*M_PI*$fortunenum/count(FORTUNES)),
            127+127*sin(2*M_PI*$fortunenum/count(FORTUNES)+2/3*M_PI),
            127+127*sin(2*M_PI*$fortunenum/count(FORTUNES)+4/3*M_PI));
        $com = "$com<br /><br /><font color=\"#$fortcol\"><b>Your fortune: ".FORTUNES[$fortunenum]."</b></font>";
    } else {
        $com = "$com<br /><br /><font color=\"#F00\"><b>DON'T TRY TO CHEAT THE SYSTEM!</b></font>";
    }
}

function cleanComment(&$com, &$upfile_status, &$is_admin, &$dest){
        // Text trimming
    if((strlenUnicode($com) > COMM_MAX) && !$is_admin){
        error(_T('regist_commenttoolong'), $dest);
    }
    $com = CleanStr($com, $is_admin); // The$ is_admin parameter is introduced because when the administrator starts, the administrator is allowed to set whether to use HTML according to config.
    if(!$com && $upfile_status==4){ 
        error(TEXTBOARD_ONLY?'ERROR: No text entered.':_T('regist_withoutcomment'));
    }
    $com = str_replace(array("\r\n", "\r"), "\n", $com);
    $com = preg_replace("/\n((　| )*\n){3,}/", "\n", $com);
 
    if(!BR_CHECK || substr_count($com,"\n") < BR_CHECK){
        $com = nl2br($com); // Newline characters are replaced by <br />
    }
    $com = str_replace("\n", '', $com); // If there are still \n newline characters, cancel the newline
}

function applyPostFilters($preview, &$com, &$email){
    if(AUTO_LINK){
        $com = auto_link($com);
    }
    if (FORTUNES && stristr($email, 'fortune')) {
        applyFortune($preview, $com, $email);
    }
    if (ROLL && stristr($email, 'roll')) {
        applyRoll($preview, $com, $email);
    }
}
 
function addDefaultText(&$sub, &$com){
    // Default content
    if(!$sub || preg_match("/^[ |　|]*$/", $sub)){
        $sub = DEFAULT_NOTITLE;
    }
 
    if(!$com || preg_match("/^[ |　|\t]*$/", $com)){
        $com = DEFAULT_NOCOMMENT;
    }
}
 
function generatePostDay(&$time){
    $youbi = array(_T('sun'),_T('mon'),_T('tue'),_T('wed'),_T('thu'),_T('fri'),_T('sat'));
    $yd = $youbi[gmdate('w', $time+TIME_ZONE*60*60)];
    return gmdate('Y/m/d', $time+TIME_ZONE*60*60).'('.(string)$yd.')'.gmdate('H:i:s', $time+TIME_ZONE*60*60);
}
function generatePostID(&$email, &$now, &$time, &$resto, &$PIO){
	$AccountIO = PMCLibrary::getAccountIOInstance();
    if(DISP_ID){ // ID
        if($AccountIO->valid() == LEV_ADMIN and DISP_ID == 2) return' ID:ADMIN'; 
        elseif($AccountIO->valid() == LEV_MODERATOR and DISP_ID == 2) return ' ID:MODERATOR'; 
        elseif(stristr($email, 'sage') and DISP_ID == 2) return ' ID:Heaven';
        else {
            switch (ID_MODE) {
                case 0:                 
                    return ' ID:'.substr(crypt(md5(getREMOTE_ADDR().IDSEED.($resto?$resto:($PIO->getLastPostNo("beforeCommit")+1))),'id'), -8);
                    break;
                case 1:
                    return ' ID:'.substr(crypt(md5(getREMOTE_ADDR().IDSEED.($resto?$resto:($PIO->getLastPostNo("beforeCommit")+1)).gmdate('Ymd', $time+TIME_ZONE*60*60)),'id'), -8);
                    break;
            }
        }
    }
}
 
function validateForDatabase(&$pwdc, &$com, &$time, &$pass, &$host, &$upfile_name, &$md5chksum, &$dest, &$PIO){
	$AccountIO = PMCLibrary::getAccountIOInstance();	
	// Continuous submission / same additional image check
    $checkcount = 50; // Check 50 by default
    $pwdc = substr(md5($pwdc), 2, 8); // Cookies Password
    if ($AccountIO->valid()<LEV_MODERATOR or defined('VIPDEF'))  {
        if($PIO->isSuccessivePost($checkcount, $com, $time, $pass, $pwdc, $host, $upfile_name))
            error(_T('regist_successivepost'), $dest); // Continuous submission check
        if($dest){ 
            if($PIO->isDuplicateAttachment($checkcount, $md5chksum)){
                 error(_T('regist_duplicatefile'), $dest); 
            }
        } // Same additional image file check
    }
}

function threadSanityCheck(&$chktime, &$flgh, &$resto, &$PIO, &$dest, &$ThreadExistsBefore){
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
    return[$chktime];
}

function pruneOld(&$PMS, &$PIO, &$FileIO, &$delta_totalsize){
    global $LIMIT_SENSOR;
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
}

function applyAging(&$resto, &$PIO, &$time, &$chktime, &$email, &$name, &$age){
    if ($resto) {
        if ($PIO->postCount($resto) <= MAX_RES || MAX_RES==0) {
            if(!MAX_AGE_TIME || (($time - $chktime) < (MAX_AGE_TIME * 60 * 60))){
                $age = true; // Discussion threads are not expired
            }
        }
        if (NOTICE_SAGE && stristr($email, 'sage')) {
            $age = false;
            if (!CLEAR_SAGE){
                $name.= '&nbsp;<b><font color="#F00">SAGE!</font></b>';
            }
        }
    }
}

function makeThumbnailAndUpdateStats(&$delta_totalsize, &$dest, &$ext, &$tim, $tmpfile, $imgW, $imgH, $W, $H){
	$FileIO = PMCLibrary::getFileIOInstance();
	
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
            if (!empty($tmpfile)) $thObj = new ThumbWrapper($tmpfile, $imgW, $imgH);
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
}

function registValidate(){
    if(!$_SERVER['HTTP_REFERER'] || !$_SERVER['HTTP_USER_AGENT'] || preg_match("/^(curl|wget)/i", $_SERVER['HTTP_USER_AGENT']) ){
        error('You look like a robot.', '');
    }
 
    if($_SERVER['REQUEST_METHOD'] != 'POST') error(_T('regist_notpost')); // Informal POST method
}
 
function spamValidate(&$ip, &$name, &$email, &$sub, &$com){
    global $BAD_STRING;
 
    // Blocking: IP/Hostname/DNSBL Check Function
    $baninfo = '';
    if(BanIPHostDNSBLCheck($ip, $ip, $baninfo)){
        error(_T('regist_ipfiltered', $baninfo));
    }
    // Block: Restrict the text that appears (text filter?)
    foreach($BAD_STRING as $value){
        if(preg_match($value, $com) || preg_match($value, $sub) || preg_match($value, $name) || preg_match($value, $email)){
            error(_T('regist_wordfiltered'));
        }
    }
 
    // Check if you enter Sakura Japanese kana (kana = Japanese syllabary)
    foreach(array($name, $email, $sub, $com) as $anti){
        if(anti_sakura($anti)){
            error(_T('regist_sakuradetected'));
        }
    }
}
 
function processFiles(&$upfile, &$upfile_path, &$upfile_name, &$upfile_status, &$md5chksum, &$imgW, &$imgH, &$imgsize, &$W, &$H, &$fname, &$ext, &$age, &$status, &$resto, &$tim, &$preview, &$dest, &$tmpfile){
    global $BAD_FILEMD5;
    
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
        if(!is_file($dest)) 
            error(_T('regist_upload_filenotfound'), $dest);
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
        setlocale(LC_ALL,'en_US.UTF-8'); // Japanese/etc special characters trimming fix
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
		    $size = getswfsize($dest);
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
}
 
function applyTripcodeAndCapcodes(&$name, &$email, &$dest){
    $name = str_replace('&#', '&&', $name); // otherwise HTML numeric entities will explode!
    list($name, $trip, $sectrip) = str_replace('&%', '&#', explode('#',$name.'##'));
    $name = str_replace('&&', '&#', $name);
    if ($trip) {
        $trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
        $salt = strtr(preg_replace('/[^\.-z]/', '.', substr($trip.'H.',1,2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
        $trip = '!'.substr(crypt($trip, $salt), -10);
    }
    if ($sectrip) {
    	$AccountIO = PMCLibrary::getAccountIOInstance();
        if ($level=$AccountIO->valid($sectrip)) {
            // Moderator capcode
            switch ($level) {
                case LEV_JANITOR: if (JCAPCODE_FMT) $name = sprintf(JCAPCODE_FMT, $name); break;
                case LEV_MODERATOR: if (MCAPCODE_FMT) $name = sprintf(MCAPCODE_FMT, $name); break;
                case LEV_ADMIN: if (ACAPCODE_FMT) $name = sprintf(ACAPCODE_FMT, $name); break;
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
 
function runWebhooks(&$resto, &$no, &$sub){
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
}

