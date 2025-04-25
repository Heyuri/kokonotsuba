<?php
class postValidator {
	private readonly board $board;
	private readonly array $config;
	private readonly globalHTML $globalHTML;
	private readonly IPValidator $IPValidator;

	private readonly mixed $threadSingleton;

	public function __construct(board $board, array $config, globalHTML $globalHTML, IPValidator $IPValidator, mixed $threadSingleton) {
		$this->board = $board;
		$this->config = $config;
		$this->globalHTML = $globalHTML;
		$this->IPValidator = $IPValidator;

		$this->threadSingleton = $threadSingleton;
	}
	
	public function registValidate() {
    	if(!$_SERVER['HTTP_REFERER'] || !$_SERVER['HTTP_USER_AGENT'] || preg_match("/^(curl|wget)/i", $_SERVER['HTTP_USER_AGENT']) ){
    	    $this->globalHTML->error('You look like a robot.', '');
    	}
 
    	if($_SERVER['REQUEST_METHOD'] != 'POST') $this->globalHTML->error(_T('regist_notpost')); // Informal POST method
	}
 
	public function spamValidate(&$name, &$email, &$sub, &$com) {
	    // Blocking: IP/Hostname/DNSBL Check Function
	    $baninfo = '';
	    if($this->IPValidator->isBanned($baninfo)){
	        $this->globalHTML->error(_T('regist_ipfiltered', $baninfo));
	    }
	    // Block: Restrict the text that appears (text filter?)
	    foreach($this->config['BAD_STRING'] as $value){
	        if(preg_match($value, $com) || preg_match($value, $sub) || preg_match($value, $name) || preg_match($value, $email)){
	            $this->globalHTML->error(_T('regist_wordfiltered'));
	        }
	    }
	 
	    // Check if you enter Sakura Japanese kana (kana = Japanese syllabary)
	    foreach(array($name, $email, $sub, $com) as $anti){
	        if(anti_sakura($anti)){
	            $this->globalHTML->error(_T('regist_sakuradetected'));
	        }
	    }
	}

	public function validateForDatabase(&$pwdc, &$com, &$time, &$pass, &$host, &$upfile_name, &$md5chksum, &$dest, $PIO, $roleLevel){
		// Continuous submission / same additional image check
	    $checkcount = 50; // Check 50 by default
	    $pwdc = substr(md5($pwdc), 2, 8); // Cookies Password
	    if ($roleLevel < $this->config['roles']['LEV_MODERATOR'])  {
	        if($PIO->isSuccessivePost($this->board, $checkcount, $com, $time, $pass, $pwdc, $host, $upfile_name))
	           $this->globalHTML->error(_T('regist_successivepost')); // Continuous submission check
	        
			if($dest){ 
	            if($PIO->isDuplicateAttachment($this->board, $checkcount, $md5chksum)){
	            	$this->globalHTML->error(_T('regist_duplicatefile')); 
	            }
	        } // Same additional image file check
	    }
	}
	
	public function threadSanityCheck(&$chktime, &$flgh, &$resto, &$PIO, &$dest, &$ThreadExistsBefore){
	        // Determine whether the article you want to respond to has just been deleted
	    if($resto){
	        if($ThreadExistsBefore){ // If the thread of the discussion you want to reply to exists
	            if(!$this->threadSingleton->isThread($resto)){ // If the thread of the discussion you want to reply to has been deleted
	                // Update the data source in advance, and this new addition is not recorded
	                $this->board->rebuildBoard();
	                $this->globalHTML->error(_T('regist_threaddeleted'), $dest);
	            }else{ // Check that the thread is set to suppress response (by the way, take out the post time of the original post)
	                $post = $PIO->fetchPosts($resto); // [Special] Take a single article content, but the $post of the return also relies on [$i] to switch articles!
	                list($chkstatus, $chktime) = array($post[0]['status'], $post[0]['tim']);
	                $chktime = intval(substr($chktime, 0, -3)); // Remove microseconds (the last three characters)
	                $flgh = $PIO->getPostStatus($chkstatus);
	            }
	        }else $this->globalHTML->error(_T('thread_not_found'), $dest); // Does not exist
	    }
	    return[$chktime];
	}
	public function cleanComment(string $com, int $upfile_status, bool $is_admin){
        // Text trimming
		if((strlenUnicode($com) > $this->config['COMM_MAX']) && !$is_admin){
			$this->globalHTML->error(_T('regist_commenttoolong'));
		}

		if(!$com && $upfile_status==4){ 
			$this->globalHTML->error($this->config['TEXTBOARD_ONLY']?'ERROR: No text entered.':_T('regist_withoutcomment'));
		}
		
		$com = str_replace(array("\r\n", "\r"), "\n", $com);
		$com = preg_replace("/\n((　| )*\n){3,}/", "\n", $com);

		if(!$this->config['BR_CHECK'] || substr_count($com,"\n") < $this->config['BR_CHECK']){
			$com = nl2br($com); // Newline characters are replaced by <br>
		}
		$com = str_replace("\n", '', $com); // If there are still \n newline characters, cancel the newline
		
		return $com;
	}
	
	public function validateFileUploadStatus($resto, $upfile_status) {
		switch($upfile_status){
    	    case UPLOAD_ERR_OK:
    	        break;
    	    case UPLOAD_ERR_FORM_SIZE:
    	        $this->globalHTML->error('ERROR: The file is too large.(upfile)');
    	        break;
    	    case UPLOAD_ERR_INI_SIZE:
    	        $this->globalHTML->error('ERROR: The file is too large.(php.ini)');
    	        break;
    	    case UPLOAD_ERR_PARTIAL:
            	$this->globalHTML->error('ERROR: The uploaded file was only partially uploaded.');
    	        break;
    	    case UPLOAD_ERR_NO_FILE:
				if(!$resto && !isset($_POST['noimg'])) $this->globalHTML->error(_T('regist_upload_noimg'));
    	        break;
    	    case UPLOAD_ERR_NO_TMP_DIR:
    	        $this->globalHTML->error('ERROR: Missing a temporary folder.');
    	        break;
    	    case UPLOAD_ERR_CANT_WRITE:
    	        $this->globalHTML->error('ERROR: Failed to write file to disk.');
    	        break;
    	    default:
    	        $this->globalHTML->error('ERROR: Unable to save the uploaded file.');
    	}
	}

    function pruneOld(&$moduleEngine, &$PIO, &$FileIO){
        // Deletion of old articles
        if(PIOSensor::check($this->board, 'delete', $this->config['LIMIT_SENSOR'])){
            $delarr = PIOSensor::listee($this->board, 'delete', $this->config['LIMIT_SENSOR']);
            if(count($delarr)){
                $moduleEngine->useModuleMethods('PostOnDeletion', array($delarr, 'recycle')); // "PostOnDeletion" Hook Point
                $files = $PIO->removePosts($delarr);
                if(count($files)) $FileIO->deleteImage($files, $this->board); // Update delta value
            }
        }

        // Additional image file capacity limit function is enabled: delete oversized files
        if($this->config['STORAGE_LIMIT'] && $this->config['STORAGE_MAX'] > 0){
            $tmp_total_size = $FileIO->getCurrentStorageSize($this->board); // Get the current size of additional images
            if($tmp_total_size > $this->config['STORAGE_MAX']){
                $files = $PIO->delOldAttachments($this->board, $tmp_total_size, $this->config['STORAGE_MAX'], false);
                $FileIO->deleteImage($files, $this->board);
            }
        }
    }


		
}
