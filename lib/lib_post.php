<?php
//post lib

function applyRoll(&$com, &$email){
	$com = "$com\n<p class=\"roll\">[NUMBER: ".rand(1,10000)."]</p>";
	$email = preg_replace('/^roll( *)/i', '', $email);
}

function applyFortune($config, &$com, &$email){
	$fortunenum=array_rand($config['FORTUNES']);
	$fortcol=sprintf("%02x%02x%02x",
		127+127*sin(2*M_PI*$fortunenum/count($config['FORTUNES'])),
		127+127*sin(2*M_PI*$fortunenum/count($config['FORTUNES'])+2/3*M_PI),
		127+127*sin(2*M_PI*$fortunenum/count($config['FORTUNES'])+4/3*M_PI));
	$com = "$com<p class=\"fortune\" style=\"color: #$fortcol;\">Your fortune: ".$config['FORTUNES'][$fortunenum]."</p>";
}

function applyPostFilters($config, $globalHTML, &$com, &$email){
	if($config['AUTO_LINK']){
		$com = $globalHTML->auto_link($com);
	}
	if ($config['FORTUNES'] && stristr($email, 'fortune')) {
		applyFortune($config, $com, $email);
	}
	if ($config['ROLL'] && stristr($email, 'roll')) {
		applyRoll($com, $email);
	}
}
 
function addDefaultText($config, &$sub, &$com){
	// Default content
	if(!$sub || preg_match("/^[ |　|]*$/", $sub)){
		$sub = $config['DEFAULT_NOTITLE'];
	}
 
	if(!$com || preg_match("/^[ |　|\t]*$/", $com)){
		$com = $config['DEFAULT_NOCOMMENT'];
	}
}
 
function generatePostDay($config, &$time){
	$youbi = array(_T('sun'),_T('mon'),_T('tue'),_T('wed'),_T('thu'),_T('fri'),_T('sat'));
	$yd = $youbi[gmdate('w', $time+$config['TIME_ZONE']*60*60)];
	return '<span class="postDate">'.gmdate('Y/m/d', $time+$config['TIME_ZONE']*60*60).'</span><span class="postDay">('.(string)$yd.')</span><span class="postTime">'.gmdate('H:i:s', $time+$config['TIME_ZONE']*60*60).'</span>';
}

function generatePostID($roleLevel, $config, &$email, &$now, &$time, &$resto, &$PIO){
	if($config['DISP_ID']){ // ID
		if($roleLevel == $config['roles']['LEV_ADMIN'] and $config['DISP_ID'] == 2) return' ID:ADMIN'; 
		elseif($roleLevel == $config['roles']['LEV_MODERATOR'] and $config['DISP_ID'] == 2) return ' ID:MODERATOR'; 
		elseif(stristr($email, 'sage') and $config['DISP_ID'] == 2) return ' ID:Heaven';
		else {
			switch ($config['ID_MODE']) {
				case 0:                 
					return ' ID:'.substr(crypt(md5(new IPAddress.$config['IDSEED'].($resto?$resto:($PIO->getLastPostNo("beforeCommit")+1))),'id'), -8);
					break;
				case 1:
					return ' ID:'.substr(crypt(md5(new IPAddress.$config['IDSEED'].($resto?$resto:($PIO->getLastPostNo("beforeCommit")+1)).gmdate('Ymd', $time+$config['TIME_ZONE']*60*60)),'id'), -8);
					break;
			}
		}
	}
}
 




function applyAging($config, &$resto, &$PIO, &$time, &$chktime, &$email, &$name, &$age){
	if ($resto) { 
		if ($PIO->getPostCountFromThread($resto) <= $config['MAX_RES'] || $config['MAX_RES'] == 0) {
			if(!$config['MAX_AGE_TIME'] || (($time - $chktime) < ($config['MAX_AGE_TIME'] * 60 * 60))){
				$age = true; // Discussion threads are not expired
			}
		}
		if ($config['NOTICE_SAGE'] && stristr($email, 'sage')) {
			$age = false;
			if (!$config['CLEAR_SAGE']){
				$name.= '&nbsp;<b><font color="#F00">SAGE!</font></b>';
			}
		}
	}
}

function makeThumbnailAndUpdateStats($boardBeingPostedTo, $config, $FileIO, &$dest, &$ext, &$tim, $tmpfile, $imgW, $imgH, $W, $H){
	if($dest && is_file($dest)){
		$destFile = $boardBeingPostedTo->getBoardUploadedFilesDirectory().$config['IMG_DIR'].$tim.$ext;
		$thumbFile = $boardBeingPostedTo->getBoardUploadedFilesDirectory().$config['THUMB_DIR'].$tim.'s.'.$config['THUMB_SETTING']['Format'];
		if($config['USE_THUMB'] !== 0){ // Generate preview image
			$thumbType = $config['USE_THUMB']; if($config['USE_THUMB']==1){ $thumbType = $config['THUMB_SETTING']['Method']; }
			require(__DIR__.'/thumb/thumb.'.$thumbType.'.php');
			if (!empty($tmpfile)) $thObj = new ThumbWrapper($tmpfile, $imgW, $imgH);
			else $thObj = new ThumbWrapper($dest, $imgW, $imgH);
			$thObj->setThumbnailConfig($W, $H, $config['THUMB_SETTING']);
			$thObj->makeThumbnailtoFile($thumbFile);
			chmod($thumbFile, 0666);
			unset($thObj);
		}
	   
		rename($dest, $destFile);
		if(file_exists($destFile)){
			$FileIO->uploadImage($tim.$ext, $destFile, filesize($destFile), $boardBeingPostedTo);
		}
		if(file_exists($thumbFile)){
			$FileIO->uploadImage($tim.'s.'.$config['THUMB_SETTING']['Format'], $thumbFile, filesize($thumbFile), $boardBeingPostedTo);
		}
	}
}

function processFiles($board, &$postValidator, &$globalHTML, &$upfile, &$upfile_path, &$upfile_name, &$upfile_status, &$md5chksum, &$imgW, &$imgH, &$imgsize, &$W, &$H, &$fname, &$ext, &$age, &$status, &$resto, &$tim, &$dest, &$tmpfile){
	$config = $board->loadBoardConfig();
	
	$upfile = $globalHTML->CleanStr($_FILES['upfile']['tmp_name']??'');
	$upfile_path = $_POST['upfile_path']??'';
	$upfile_name = $_FILES['upfile']['name']??'';
	$upfile_status = $_FILES['upfile']['error']??UPLOAD_ERR_NO_FILE;
 
	// Determine the upload status
	$postValidator->validateFileUploadStatus($resto, $upfile_status);
 	
	// If there is an uploaded file, process the additional image file
	if($upfile && (is_uploaded_file($upfile) || is_file($upfile))){
		// 1. Save the file first
		$dest = $board->getBoardStoragePath().$tim.'.tmp';
		move_uploaded_file($upfile, $dest) or copy($upfile, $dest);
		chmod($dest, 0666);
		if(!is_file($dest)) 
			$globalHTML->error(_T('regist_upload_filenotfound'), $dest);
		// Remove exif
		if (function_exists('exif_read_data') && function_exists('exif_imagetype')) {
			$imageType = exif_imagetype($dest);
 
			if ($imageType == IMAGETYPE_JPEG) {
				$exif = exif_read_data($dest);
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
			if(($upsizeTTL - $upsizeHDR) > $config['HTTP_UPLOAD_DIFF']){
				if($config['KILL_INCOMPLETE_UPLOAD']){
					unlink($dest);
					die(_T('regist_upload_killincomp')); // The prompt to the browser, if the user still sees it, will not be puzzled
				}else $up_incomplete = 1;
			}
		}
		// 3. Check whether it is an acceptable file
		$size = getimagesize($dest);
		$imgsize = filesize($dest); // File size
		if ($imgsize > $config['MAX_KB']*1024) $globalHTML->error(_T('regist_upload_exceedcustom'));
		$imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // Discrimination of KB and B
		setlocale(LC_ALL,'en_US.UTF-8'); // Japanese/etc special characters trimming fix
		$fname = $globalHTML->Cleanstr(pathinfo($upfile_name, PATHINFO_FILENAME));
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
						$size[0]=$config['MAX_W'];
						$size[1]=$config['MAX_H'];
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
			$video_exts = explode('|', strtolower($config['VIDEO_EXT']));
			if(array_search(substr($ext, 1), $video_exts)!==false) {
				// Video thumbs
				$tmpfile = tempnam(sys_get_temp_dir(), "thumbnail_");
				rename($tmpfile, $tmpfile.".jpg");
				$tmpfile .= ".jpg";
			   
				exec("ffmpeg -y -i ".$dest." -ss 00:00:1 -vframes 1 ".$tmpfile." 2>&1");
			 
				$size = getimagesize($tmpfile);
				$imgsize = filesize($dest); // File size
				$imgsize = ($imgsize>=1024) ? (int)($imgsize/1024).' KB' : $imgsize.' B'; // Discrimination of KB and B
			}
		}
		$allow_exts = explode('|', strtolower($config['ALLOW_UPLOAD_EXT'])); // Accepted additional image file extension
		if(array_search(substr($ext, 1), $allow_exts)===false) $globalHTML->error(_T('regist_upload_notsupport'), $dest); // Uploaded file not allowed due to wrong file extension
		// Block setting: Restrict the upload of MD5 checkcodes for additional images
		$md5chksum = md5_file($dest); // File MD%
		if(array_search($md5chksum, $config['BAD_FILEMD5'])!==false) $globalHTML->error(_T('regist_upload_blocked'), $dest); // If the MD5 checkcode of the uploaded file is in the block list, the upload is blocked
 
		// 4. Calculate the thumbnail display size of the additional image file
		$W = $imgW = $size[0];
		$H = $imgH = $size[1];
		$MAXW = $resto ? $config['MAX_RW'] : $config['MAX_W'];
		$MAXH = $resto ? $config['MAX_RH'] : $config['MAX_H'];
		if($W > $MAXW || $H > $MAXH){
			$W2 = $MAXW / $W;
			$H2 = $MAXH / $H;
			$key = ($W2 < $H2) ? $W2 : $H2;
			$W = ceil($W * $key);
			$H = ceil($H * $key);
		}
		if ($ext=='.swf') $W = $H = 0; // dumb flash file thinks it's an image lol.
		$mes = _T('regist_uploaded', $globalHTML->CleanStr($upfile_name));
	}
}

/* Catch impersonators and modify name to display such */ 
function catchFraudsters(&$name) {
	if (preg_match('/[◆◇♢♦⟡★]/u', $name)) $name .= " (fraudster)";
}
 
function applyTripcodeAndCapcodes($config, $globalHTML, $staffSession, &$name, &$email, &$dest){
	catchFraudsters($name);
	
	$name = str_replace('&#', '&&', $name);
	list($name, $trip, $sectrip) = str_replace('&%', '&#', explode('#',$name.'##'));
	$name = str_replace('&&', '&#', $name);
	

	// Moderator capcode
	$capcodeRoleLevel = $staffSession->getRoleLevel();

	if($capcodeRoleLevel >= $config['roles']['LEV_JANITOR'] && $sectrip) {
		$roleMap = [
				'LEV_JANITOR'		=> 'JCAPCODE_FMT',
				'LEV_MODERATOR'	=> 'MCAPCODE_FMT',
				'LEV_ADMIN'			=> 'ACAPCODE_FMT',
		];

		$matchedCapcode = false;

		foreach ($roleMap as $roleKey => $formatKey) {
				$roleLevel = $config['roles'][$roleKey];
				$roleName = $globalHTML->roleNumberToRoleName($roleLevel);

				if ($capcodeRoleLevel >= $roleLevel && $sectrip === ' ' . $roleName) {
						if (!empty($config[$formatKey])) {
								$name = sprintf($config[$formatKey], $name);
								$name = "<span class=\"postername\">$name</span>";
								$matchedCapcode = true;
								break;
						}
				}
		}

		if ($matchedCapcode) {
				return;
		}
	}

	if ($trip) {
		$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
		$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($trip.'H.',1,2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
		
		$tripcodeCrypt = substr(crypt($trip, $salt), -10);
		$trip = "◆$tripcodeCrypt";
	}
	if ($sectrip) {
			// User
			$sha =str_rot13(base64_encode(pack("H*",sha1($sectrip.$config['TRIPSALT']))));
			$sha = substr($sha,0,10);
			$trip = "★$sha";
	}

	if(!$name || preg_match("/^[ |　|]*$/", $name)){
		if($config['ALLOW_NONAME']) $name = $config['DEFAULT_NONAME'];
		else $globalHTML->error(_T('regist_withoutname'), $dest);
	}
	$name = "<span class=\"postername\">$name</span><span class=\"postertrip\">$trip</span>";
	if (isset($config['CAPCODES'][$trip])) {
		$capcode = $config['CAPCODES'][$trip];
		$name = '<span class="capcodeSection" style="color:'.$capcode['color'].';">'.$name.'<span class="postercap">'.$capcode['cap'].'</span>'.'</span>';
	}
	
	if(stristr($email, 'vipcode') && defined('VIPDEF')) {
			$name .= ' <img src="'.$config['STATIC_URL'].'vip.gif" title="This user is a VIP user" style="vertical-align: middle;margin-top: -2px;" alt="VIP">'; 
	}
	
	$email = preg_replace('/^vipcode$/i', '', $email);

}

function runWebhooks($board, &$resto, &$no, &$sub){
	$config = $board->loadBoardConfig();
	$globalHTML = new globalHTML($board);
	// webhooks
	if(!empty($config['IRC_WH'])){
		$url = 'https:'.$globalHTML->fullURL().$config['PHP_SELF']."?res=".($resto?$resto:$no)."#p$no";
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
		file_get_contents($config['IRC_WH'], false, $stream);
	}
	if(!empty($config['DISCORD_WH'])){
		$url = 'https:'.$globalHTML->fullURL().$config['PHP_SELF']."?res=".($resto?$resto:$no)."#p$no";
		$stream = stream_context_create([
			'http'=>[
				'method'=>'POST',
				'header'=>'content-type:application/x-www-form-urlencoded',
				'content'=>http_build_query([
					'content'=>($resto?'New post':'New thread')." <$url>",
				]),
			]
		]);
		file_get_contents($config['DISCORD_WH'], false, $stream);
	}
 
			// webhooks with titles
	if(!empty($config['IRC_WH_NEWS']) && !$resto){
		$url = 'https:'.$globalHTML->fullURL().$config['PHP_SELF']."?res=".($resto?$resto:$no);
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
		file_get_contents($config['IRC_WH_NEWS'], false, $stream);
	}
	
	if(!empty($config['DISCORD_WH_NEWS']) && !$resto){
		$url = 'https:'.$globalHTML->fullURL().$config['PHP_SELF']."?res=".($resto?$resto:$no);
		$stream = stream_context_create([
			'http'=>[
				'method'=>'POST',
				'header'=>'content-type:application/x-www-form-urlencoded',
				'content'=>http_build_query([
					'content'=>($resto?'New post':''." '$sub'")." <$url>",
				]),
			]
		]);
		file_get_contents($config['DISCORD_WH_NEWS'], false, $stream);
	}
}

function searchBoardArrayForBoard($boards, $targetBoardUID) {
	foreach ($boards as $board) {
		if ($board->getBoardUID() === intval($targetBoardUID)) {
			return $board;
		}
	}
}

function createBoardStoredFilesFromArray($posts) {
	$boardIO = boardIO::getInstance();

	$boards = $boardIO->getAllBoards();
	$files = [];
	foreach($posts as $post) {
		$board = searchBoardArrayForBoard($boards, $post['boardUID']);

		$files[] = new boardStoredFile($post['tim'], $post['ext'], $board);
	}
	return $files;
}