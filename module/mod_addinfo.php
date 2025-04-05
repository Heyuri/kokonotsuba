<?php
class mod_addinfo extends moduleHelper {
	private $mypage, $dotpoints;

	public function __construct($moduleEngine) {
		parent::__construct($moduleEngine);
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
		$addinfoHTML .= '</ul><hr><ul class="rules">';
		//begin list
		foreach($this->dotpoints as $rule) {
			$addinfoHTML .= '<li>'.$rule.'</li>';
		}
		
		$form .= $addinfoHTML;
	}
	
}
