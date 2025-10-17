<?php
class postValidator {
	public function __construct(
		private board $board,
		private readonly array $config,
		private readonly IPValidator $IPValidator,
		private readonly threadRepository $threadRepository,
		private readonly softErrorHandler $softErrorHandler,
		private readonly threadService $threadService,
		private readonly postService $postService,
		private readonly attachmentService $attachmentService,
		private readonly mixed $FileIO) {}
	
	public function registValidate(): void {
		if(!$_SERVER['HTTP_REFERER'] || !$_SERVER['HTTP_USER_AGENT'] || preg_match("/^(curl|wget)/i", $_SERVER['HTTP_USER_AGENT']) ){
			$this->softErrorHandler->errorAndExit('You look like a robot.');
		}
 
		if($_SERVER['REQUEST_METHOD'] != 'POST') $this->softErrorHandler->errorAndExit(_T('regist_notpost')); // Informal POST method
	
	}
 
	public function spamValidate(&$name, &$email, &$sub, &$com) {
		// Blocking: IP/Hostname/DNSBL Check Function
		$baninfo = '';
		if($this->IPValidator->isBanned($baninfo)){
			$this->softErrorHandler->errorAndExit(_T('regist_ipfiltered', $baninfo));
		}
		// Block: Restrict the text that appears (text filter?)
		foreach($this->config['BAD_STRING'] as $value){
			if(preg_match($value, $com) || preg_match($value, $sub) || preg_match($value, $name) || preg_match($value, $email)){
				$this->softErrorHandler->errorAndExit(_T('regist_wordfiltered'));
			}
		}
	 
		// Check if you enter Sakura Japanese kana (kana = Japanese syllabary)
		foreach(array($name, $email, $sub, $com) as $anti){
			if(anti_sakura($anti)){
				$this->softErrorHandler->errorAndExit(_T('regist_sakuradetected'));
			}
		}
	}

	public function validateForDatabase(&$pwdc, &$com, &$time, &$pass, &$host, &$upfile_name, &$md5chksum, &$dest, $roleLevel){
		// Continuous submission / same additional image check
		$checkcount = 50; // Check 50 by default
		$pwdc = substr(md5($pwdc), 2, 12); // Cookies Password
		if ($roleLevel->isLessThan(\Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR))  {
			if($this->postService->isSuccessivePost($this->board, $checkcount, $com, $time, $pass, $pwdc))
			   $this->softErrorHandler->errorAndExit(_T('regist_successivepost')); // Continuous submission check
			
			if($dest && $this->config['PREVENT_DUPLICATE_FILE_UPLOADS']) { 
				if($this->attachmentService->isDuplicateAttachment($this->board, $checkcount)){
					$this->softErrorHandler->errorAndExit(_T('regist_duplicatefile')); 
				}
			} // Same additional image file check
		}
	}
	
	public function threadSanityCheck(&$postOpRoot, &$flgh, &$thread_uid, &$resno, &$ThreadExistsBefore){
		if($resno && !$ThreadExistsBefore) {
			// Update the data source in advance, and this new addition is not recorded
			$this->softErrorHandler->errorAndExit(_T('regist_threaddeleted'), 404);
		} 
		
		// Determine whether the article you want to respond to has just been deleted
		if($thread_uid){
			if($ThreadExistsBefore) { // If the thread of the discussion you want to reply to exists
				// Check that the thread is set to suppress response (by the way, take out the post time of the original post)
				$post = $this->threadRepository->getPostsFromThread($thread_uid)[0]; // [Special] Take a single article content, but the $post of the return also relies on [$i] to switch articles!

				[$postOpStatus, $postOpRoot] = array($post['status'], $post['root']);
				$flgh = new FlagHelper($postOpStatus);
			} else {
				$this->softErrorHandler->errorAndExit(_T('thread_not_found'), 404); // Does not exist
			}
		}
		return[$postOpRoot];
	}
	public function cleanComment(string $com, int $upfile_status, bool $is_admin){
		// Text trimming
		if((strlenUnicode($com) > $this->config['COMM_MAX']) && !$is_admin){
			$this->softErrorHandler->errorAndExit(_T('regist_commenttoolong'));
		}

		if(!$com && $upfile_status === UPLOAD_ERR_NO_FILE){ 
			$this->softErrorHandler->errorAndExit($this->config['TEXTBOARD_ONLY']?'ERROR: No text entered.':_T('regist_withoutcomment'));
		}
		
		$com = str_replace(array("\r\n", "\r"), "\n", $com);
		$com = preg_replace("/\n((　| )*\n){3,}/", "\n", $com);

		if(!$this->config['BR_CHECK'] || substr_count($com,"\n") < $this->config['BR_CHECK']){
			$com = nl2br($com); // Newline characters are replaced by <br>
		}
		$com = str_replace("\n", '', $com); // If there are still \n newline characters, cancel the newline

		return $com;
	}

	public function pruneOld(?array $threadUidList): void {
		// Delete old threads
		if(!empty($threadUidList)) {
			$this->threadService->pruneByAmount($threadUidList, $this->config['MAX_THREAD_AMOUNT']);
		}

		// Additional image file capacity limit function is enabled: delete oversized files
		if($this->config['STORAGE_LIMIT'] && $this->config['STORAGE_MAX'] > 0){
			$tmp_total_size = $this->FileIO->getCurrentStorageSize($this->board); // Get the current size of additional images
			if($tmp_total_size > $this->config['STORAGE_MAX']){
				$files = $this->attachmentService->delOldAttachments($this->board, $tmp_total_size, $this->config['STORAGE_MAX'], false);
				$this->FileIO->deleteImage($files, $this->board);
			}
		}
	}


		
}
