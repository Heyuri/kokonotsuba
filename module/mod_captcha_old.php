<?php
/*
mod_captcha.php

The original concept came from various technical discussion forums, and various ideas were mixed and implemented.

The main basis is the content of this page:
http://jmhoule314.blogspot.com/2006/05/easy-php-captcha-tutorial-today-im.html
Added the result of Kris Knigga's modification in response.
*/
class mod_captcha extends ModuleHelper {
	private $CAPTCHA_WIDTH = 100; // Picture width
	private $CAPTCHA_HEIGHT = 25; // Picture height
	private $CAPTCHA_LENGTH = 4; // Number of plain words
	private $CAPTCHA_GAP = 20; // Clear code character spacing
	private $CAPTCHA_TEXTY = 20; // character vertical position
	private $CAPTCHA_FONTMETHOD = 0; // Types of fonts used (0: GDF (*.gdf) 1: TrueType Font (*.ttf))
	private $CAPTCHA_FONTFACE = array(); // Fonts used (can be selected randomly, but the font types need to be the same and cannot be mixed)
	private $CAPTCHA_ECOUNT = 2;
	private $ALT_POSTAREA = '';
	private $LANGUAGE=array(
			'zh_TW' => array(
				'modcaptcha_captcha' => '發文驗證碼',
				'modcaptcha_reload' => '看不懂？重讀',
				'modcaptcha_enterword' => '<small>(請輸入你在圖中看到的文字 大小寫不分)</small>',
				'modcaptcha_captcha_alt' => 'CAPTCHA 驗證碼圖像',
				'modcaptcha_worderror' => '您輸入的驗證碼錯誤！'
			),
			'ja_JP' => array(
				'modcaptcha_captcha' => '画像認証',
				'modcaptcha_reload' => 'リロード',
				'modcaptcha_enterword' => '<br /><small>(画像に表示されている文字を入力してください。大文字と小文字は区別されません。)</small>',
				'modcaptcha_captcha_alt' => 'CAPTCHA画像',
				'modcaptcha_worderror' => '画像認証に失敗しました!'
			),
			'en_US' => array(
				'modcaptcha_captcha' => 'Captcha',
				'modcaptcha_reload' => 'Reload',
				'modcaptcha_enterword' => ' <small></small>',
				'modcaptcha_captcha_alt' => 'CAPTCHA Image',
				'modcaptcha_worderror' => 'Incorrect Captcha'
			)
		);

	public function __construct($PMS) {
		parent::__construct($PMS);

		$this->CAPTCHA_FONTFACE = array($this->config['ROOTPATH'].'module/font1.gdf'); 
		$this->ALT_POSTAREA = stristr($this->config['TEMPLATE_FILE'], 'txt');
		
		$this->mypage = $this->getModulePageURL(); 
		$this->attachLanguage($this->LANGUAGE);// Load language file
	}

	public function getModuleName(){
		return 'mod_captcha : CAPTCHA 驗證圖像機制';
	}

	public function getModuleVersionInfo(){
		return 'Koko BBS Release 1';
	}

	/* Attach CAPTCHA image and function to page */
	public function autoHookPostForm(&$form){
		if ($this->ALT_POSTAREA) $form .= '<tr class="captchaarea"><td valign="TOP"><label for="captchacode">Captcha:</label></td><td><img src="'.$this->mypage.'" alt="'._T('modcaptcha_captcha_alt').'" id="chaimg" /><small> [<a href="#" onclick="(function(){var i=document.getElementById(\'chaimg\'),s=i.src;i.src=s+\'&\';})();">'.$this->_T('modcaptcha_reload').'</a>]</small><br /><input tabindex="7" type="text" id="captchacode" name="captchacode" autocomplete="off" class="inputtext" />'.$this->_T('modcaptcha_enterword').'</td></tr>';
		else $form .= '<tr><td class="postblock"><b><label for="captchacode">Captcha</label></b></td><td><img src="'.$this->mypage.'" alt="'._T('modcaptcha_captcha_alt').'" id="chaimg" /><small> [<a href="#" onclick="(function(){var i=document.getElementById(\'chaimg\'),s=i.src;i.src=s+\'&\';})();">'.$this->_T('modcaptcha_reload').'</a>]</small><br /><input tabindex="7" type="text" id="captchacode" name="captchacode" autocomplete="off" class="inputtext" />'.$this->_T('modcaptcha_enterword').'</td></tr>';
	}

	/* Check whether the light and dark codes meet the requirements immediately after receiving the request */
	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo){
		if (defined('VIPDEF')) return;
		if (valid()>=$this->config['roles']['LEV_JANITOR']) return; //no captcha for admin mode
		@session_start();
		$MD5code = isset($_SESSION['captcha_dcode']) ? $_SESSION['captcha_dcode'] : false;
		if($MD5code===false || !isset($_POST['captchacode']) || md5(strtoupper($_POST['captchacode'])) !== $MD5code){ // Case insensitive check
			unset($_SESSION['captcha_dcode']);
			error($this->_T('modcaptcha_worderror'));
		}
	}

	public function ModulePage(){
		$this->OutputCAPTCHA(); // Generate password, CAPTCHA image
	}

	/* Generate CAPTCHA image, clear code, password and embedded script */
	private function OutputCAPTCHA(){
		@session_start();

		// Randomly generate clear and secret codes
		$byteTable = Array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'); // Clearly define the array
		$LCode = ''; // Clear code
		for($i = 0; $i < $this->CAPTCHA_LENGTH; $i++) $LCode .= $byteTable[rand(0, count($byteTable) - 1)]; // Random draw code
		$DCode = md5($LCode); // Password (MD5 with clear code)
		$_SESSION['captcha_dcode'] = $DCode; // Password is stored in session

		// Generate staging image
		$captcha = ImageCreateTrueColor($this->CAPTCHA_WIDTH, $this->CAPTCHA_HEIGHT);
		$randcolR = rand(100, 230); $randcolG = rand(100, 230); $randcolB = rand(100, 230); // Random color code value
		$backColor = ImageColorAllocate($captcha, $randcolR, $randcolG, $randcolB); // Background color
		ImageFill($captcha, 0, 0, $backColor); // Fill in the background color
		$txtColor = ImageColorAllocate($captcha, $randcolR - 40, $randcolG - 40, $randcolB - 40); // Text color
		$rndFontCount = count($this->CAPTCHA_FONTFACE); // Random number of fonts

		// Type text
		for($p = 0; $p < $this->CAPTCHA_LENGTH; $p++){
			if($this->CAPTCHA_FONTMETHOD){ // TrueType Font
				// Set the rotation angle (left or right)
		    	if(rand(1, 2)==1) $degree = rand(0, 25);
		    	else $degree = rand(335, 360);
				// Layer, font size, rotation angle, X-axis, Y-axis (counting from the bottom left of the word), font color, font, printed text
				ImageTTFText($captcha, rand(14, 16), $degree, ($p + 1) * $this->CAPTCHA_GAP, $this->CAPTCHA_TEXTY, $txtColor, $this->CAPTCHA_FONTFACE[rand(0, $rndFontCount - 1)], substr($LCode, $p, 1));
			}else{ // GDF font
				$font = ImageLoadFont($this->CAPTCHA_FONTFACE[rand(0, $rndFontCount - 1)]);
				// Layer, font, X-axis, Y-axis (counting from the top left of the word), printed text, font color
				ImageString($captcha, $font, ($p + 1) * $this->CAPTCHA_GAP, $this->CAPTCHA_TEXTY - 18, substr($LCode, $p, 1), $txtColor);
			}
		}

		// For confusion (draw ellipse)
		for($n = 0; $n < $this->CAPTCHA_ECOUNT; $n++){
	    	ImageEllipse($captcha, rand(1, $this->CAPTCHA_WIDTH), rand(1, $this->CAPTCHA_HEIGHT), rand(50, 100), rand(12, 25), $txtColor);
	    	ImageEllipse($captcha, rand(1, $this->CAPTCHA_WIDTH), rand(1, $this->CAPTCHA_HEIGHT), rand(50, 100), rand(12, 25), $backColor);
		}

		// Output image
		header('Content-Type: image/png');
		header('Cache-Control: no-cache');
		ImagePNG($captcha);
		ImageDestroy($captcha);
	}
}
