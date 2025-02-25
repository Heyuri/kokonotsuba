<?php
class mod_addinfo extends ModuleHelper {
	private $mypage, $usercounter, $timeout;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->dotpoints = $this->config['ModuleSettings']['ADD_INFO'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.': Additional Info';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}

	public function autoHookPostInfo(&$form) {
		$addinfoHTML = '';	
		$addinfoHTML .= '<hr>';
		//begin list
		foreach($this->dotpoints as $rule) {
			$addinfoHTML .= '<li>'.$rule.'</li>';
		}
		
		$form .= $addinfoHTML;
	}
	
}
