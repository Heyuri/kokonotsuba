<?php

/*
	mod_ads.php
	By: bobman (Yahoo! ^_^)
*/

class mod_ads implements IModule {
	private static $PMS;
	private static $SELF;
	private $config;
	
	public function __construct($PMS) {
		global $config;
		self::$PMS = $PMS;

		$this->config = $config;
	}

	// Names
	public function getModuleName() {
		return __CLASS__.' : Kokonotsuba Ads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	// Top Ad
	public function autoHookThreadFront(&$txt) {		
		$txt .= '<center>
		<iframe id="spasob" src="'.$this->config['STATIC_URL'].'image/fullbanners/fullbanners.php" style="max-width: 100%;" frameborder="0" scrolling="no" width="468" height="60" style="border: 1px solid #000000;"></iframe>
		</center>
		<hr size="1" />'."\n";
	}

	// Bottom Ad
	public function autoHookThreadRear(&$txt) {
		//$txt .= '<center><a href="#">[AD] #02！</a></center><hr size="1" />'."\n";
	}
	
}
