<?php

/*
	mod_ads.php
	By: bobman (Yahoo! ^_^)
*/

class mod_ads extends ModuleHelper {
	private $mypage;
	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();

		$this->config['ModuleSettings']['SHOW_TOP_AD'] = isset($config['ModuleSettings']['SHOW_TOP_AD']) ? $config['ModuleSettings']['SHOW_TOP_AD'] : true;
		$this->config['ModuleSettings']['SHOW_BOTTOM_AD'] = isset($config['ModuleSettings']['SHOW_BOTTOM_AD']) ? $config['ModuleSettings']['SHOW_BOTTOM_AD'] : true;
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
		if ($this->config['ModuleSettings']['SHOW_TOP_AD']) { // Check if top ad is enabled
			$txt .= '<iframe id="fullbannerIframeTop" class="fullbannerIframe" title="Banner" src="' . $this->config['STATIC_URL'] . 'image/fullbanners/fullbanners.php"></iframe><hr class="hrAds">'."\n";
		}
	}

	// Bottom Ad
	public function autoHookThreadRear(&$txt) {
		if ($this->config['ModuleSettings']['SHOW_BOTTOM_AD']) { // Check if bottom ad is enabled
			$txt .= '<iframe id="fullbannerIframeBottom" class="fullbannerIframe" title="Banner" src="' . $this->config['STATIC_URL'] . 'image/fullbanners/fullbanners.php"></iframe><hr class="hrAds">'."\n";
		}
	}
	
}
