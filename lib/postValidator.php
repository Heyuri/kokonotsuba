<?php
class postValidator {
	private $dbSettings, $config, $board;

	public function __construct($board) {
		$this->board = $board;
		$this->config = $board->loadBoardConfig();
	}
	
	public function registValidate() {
		$globalHTML = new globalHTML($this->board);
    	if(!$_SERVER['HTTP_REFERER'] || !$_SERVER['HTTP_USER_AGENT'] || preg_match("/^(curl|wget)/i", $_SERVER['HTTP_USER_AGENT']) ){
    	    $globalHTML->error('You look like a robot.', '');
    	}
 
    	if($_SERVER['REQUEST_METHOD'] != 'POST') error(_T('regist_notpost')); // Informal POST method
	}
 
	public function spamValidate(&$ip, &$name, &$email, &$sub, &$com){
	    $globalHTML = new globalHTML($this->board);
	    // Blocking: IP/Hostname/DNSBL Check Function
	    $baninfo = '';
	    if(BanIPHostDNSBLCheck($this->config, $ip, $ip, $baninfo)){
	        $globalHTML->error(_T('regist_ipfiltered', $baninfo));
	    }
	    // Block: Restrict the text that appears (text filter?)
	    foreach($config['BAD_STRING'] as $value){
	        if(preg_match($value, $com) || preg_match($value, $sub) || preg_match($value, $name) || preg_match($value, $email)){
	            error(_T('regist_wordfiltered'));
	        }
	    }
	 
	    // Check if you enter Sakura Japanese kana (kana = Japanese syllabary)
	    foreach(array($name, $email, $sub, $com) as $anti){
	        if(anti_sakura($anti)){
	            $globalHTML->error(_T('regist_sakuradetected'));
	        }
	    }
	}

	public function validateForDatabase(&$pwdc, &$com, &$time, &$pass, &$host, &$upfile_name, &$md5chksum, &$dest, $PIO, $roleLevel){
		$globalHTML = new globalHTML($this->board);
		// Continuous submission / same additional image check
	    $checkcount = 50; // Check 50 by default
	    $pwdc = substr(md5($pwdc), 2, 8); // Cookies Password
	    if ($roleLevel < $this->config['roles']['LEV_MODERATOR'])  {
	        if($PIO->isSuccessivePost($this->board, $checkcount, $com, $time, $pass, $pwdc, $host, $upfile_name))
	           $globalHTML->error(_T('regist_successivepost'), $dest); // Continuous submission check
	        if($dest){ 
	            if($PIO->isDuplicateAttachment($this->board, $checkcount, $md5chksum)){
	            	$globalHTML->error(_T('regist_duplicatefile'), $dest); 
	            }
	        } // Same additional image file check
	    }
	}
	
	public function threadSanityCheck(&$chktime, &$flgh, &$resto, &$PIO, &$dest, &$ThreadExistsBefore){
		$globalHTML = new globalHTML($this->board);
	        // Determine whether the article you want to respond to has just been deleted
	    if($resto){
	        if($ThreadExistsBefore){ // If the thread of the discussion you want to reply to exists
	            if(!$PIO->isThread($resto)){ // If the thread of the discussion you want to reply to has been deleted
	                // Update the data source in advance, and this new addition is not recorded
	                $this->board->rebuildBoard();
	                $globalHTML->error(_T('regist_threaddeleted'), $dest);
	            }else{ // Check that the thread is set to suppress response (by the way, take out the post time of the original post)
	                $post = $PIO->fetchPosts($resto); // [Special] Take a single article content, but the $post of the return also relies on [$i] to switch articles!
	                list($chkstatus, $chktime) = array($post[0]['status'], $post[0]['tim']);
	                $chktime = substr($chktime, 0, -3); // Remove microseconds (the last three characters)
	                $flgh = $PIO->getPostStatus($chkstatus);
	            }
	        }else $globalHTML->error(_T('thread_not_found'), $dest); // Does not exist
	    }
	    return[$chktime];
	}
	public function cleanComment(&$com, &$upfile_status, &$is_admin, &$dest){
		$globalHTML = new globalHTML($this->board);
        // Text trimming
		if((strlenUnicode($com) > $this->config['COMM_MAX']) && !$is_admin){
			$globalHTML->error(_T('regist_commenttoolong'), $dest);
		}
		$com = CleanStr($com, $is_admin); // The$ is_admin parameter is introduced because when the administrator starts, the administrator is allowed to set whether to use HTML according to config.
		if(!$com && $upfile_status==4){ 
		$globalHTML->error($this->config['TEXTBOARD_ONLY']?'ERROR: No text entered.':_T('regist_withoutcomment'));
		}
		$com = str_replace(array("\r\n", "\r"), "\n", $com);
		$com = preg_replace("/\n((　| )*\n){3,}/", "\n", $com);

		if(!$this->config['BR_CHECK'] || substr_count($com,"\n") < $this->config['BR_CHECK']){
			$com = nl2br($com); // Newline characters are replaced by <br>
		}
		$com = str_replace("\n", '', $com); // If there are still \n newline characters, cancel the newline
	}
	
	public function validateFileUploadStatus($resto, $upfile_status) {
		$globalHTML = new globalHTML($this->board);
		switch($upfile_status){
    	    case UPLOAD_ERR_OK:
    	        break;
    	    case UPLOAD_ERR_FORM_SIZE:
    	        $globalHTML->error('ERROR: The file is too large.(upfile)');
    	        break;
    	    case UPLOAD_ERR_INI_SIZE:
    	        $globalHTML->error('ERROR: The file is too large.(php.ini)');
    	        break;
    	    case UPLOAD_ERR_PARTIAL:
            	$globalHTML->error('ERROR: The uploaded file was only partially uploaded.');
    	        break;
    	    case UPLOAD_ERR_NO_FILE:
				if(!$resto && !isset($_POST['noimg'])) $globalHTML->error(_T('regist_upload_noimg'));
    	        break;
    	    case UPLOAD_ERR_NO_TMP_DIR:
    	        $globalHTML->error('ERROR: Missing a temporary folder.');
    	        break;
    	    case UPLOAD_ERR_CANT_WRITE:
    	        $globalHTML->error('ERROR: Failed to write file to disk.');
    	        break;
    	    default:
    	        $globalHTML->error('ERROR: Unable to save the uploaded file.');
    	}
	}
		
}
