<?php
/*
mod_recaptcha_v2.php
*/

/* get the keys from https://www.google.com/recaptcha/admin/create */
class mod_recaptcha_v2 extends moduleHelper {
	private $KEY_PUBLIC   = 'SITE KEY';

	public function getModuleName(){
		return 'mod_recaptcha_v2 : reCAPTCHA v2';
	}

	public function getModuleVersionInfo(){
		return '7th.Alpha.0 (v150127)';
	}

	public function autoHookHead(&$head, $isReply){
		$head.="<script src='https://www.google.com/recaptcha/api.js?hl=en-US'></script>";
	}

	/* Add reCAPTCHA function to the page */
	public function autoHookPostForm(&$txt){
		$txt .= '<tr><th class="postblock">Verification</th><td>'.'<div class="g-recaptcha" data-sitekey="'.$this->KEY_PUBLIC.'"></div>'.'</td></tr>';
	}
	
	function validateCaptcha($privatekey, $response) {
	    $responseData = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$privatekey.'&response='.$response));
	    return $responseData->success;
    }

	/* Check whether it is correct as soon as you receive the request */
	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo){
		$staffSession = new staffAccountFromSession;
		$globalHTML = new globalHTML($this->board);

		$roleLevel = $staffSession->getRoleLevel();

		if ($roleLevel->isAtLeast(\Kokonotsuba\Root\Constants\userRole::LEV_MODERATOR)) return; //no captcha for admin mode
		$resp = $this->validateCaptcha('SECRET KEY', $_POST['g-recaptcha-response']);
		if($resp == null){ $globalHTML->error('reCAPTCHA failed！You are not acting like a human!'); } // 檢查
	}
}
