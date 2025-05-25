<?php
class mod_addinfo extends moduleHelper {
	private $mypage, $dotpoints;

	public function __construct(moduleEngine $moduleEngine, boardIO $boardIO, pageRenderer $pageRenderer, pageRenderer $adminPageRenderer) {
		parent::__construct($moduleEngine, $boardIO, $pageRenderer, $adminPageRenderer);		$this->dotpoints = $this->config['ModuleSettings']['ADD_INFO'];
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
