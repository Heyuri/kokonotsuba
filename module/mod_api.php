<?php
class mod_api extends ModuleHelper {
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = str_replace('&amp;', '&', $this->getModulePageURL());
	}

	public function getModuleName() {
		return __CLASS__.' : K! JSON API';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function ModulePage() {

	}
}
