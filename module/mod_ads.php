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
		<iframe id="spasob" src="//kncdn.org/fonts.php" frameborder="0" scrolling="no" width="728" height="90" style="border: 1px solid #000000;"></iframe>
		</center>
		<hr size"1" />'."\n";
	}

	// Bottom Ad
	public function autoHookThreadRear(&$txt) {
		//$txt .= '<center><a href="#">[AD] #02！</a></center><hr size="1" />'."\n";
	}
	
}