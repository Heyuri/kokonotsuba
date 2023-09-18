<?php

/*
	mod_ads.php : KolymaNET Ads 
	By: bobman (Yahoo! ^_^)
*/

class mod_ads implements IModule {
	private static $PMS;
	private static $SELF;
	
	public function __construct($PMS) {
		self::$PMS = $PMS;
	}

	// Names
	public function getModuleName() {
		return __CLASS__.' : KolymaNET Ads';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	// Top Ad
	public function autoHookThreadFront(&$txt) {
		//$txt .= '<center><a href="#">[AD] #01！</a></center><hr size="1" />'."\n";
		
		$txt .= '<center>
		<iframe id="spasob" src="https://static.heyuri.net/image/fullbanners/fullbanners.php" max-width: 100%; frameborder="0" scrolling="no" width="468" height="60" style="border: 1px solid #000000;"></iframe>
		</center>
		<hr size="1" />'."\n";
	}

	// Bottom Ad
	public function autoHookThreadRear(&$txt) {
		//$txt .= '<center><a href="#">[AD] #02！</a></center><hr size="1" />'."\n";
	}
	
}
