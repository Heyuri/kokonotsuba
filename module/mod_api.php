<?php
class mod_api extends moduleHelper {
	private $mypage;

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);
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
