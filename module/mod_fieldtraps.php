<?php
// komeo 2024

class mod_fieldtraps extends moduleHelper {
	private $fields = array('e-mail', 'username', 'subject', 'comment', 'firstname', 'lastname', 'city', 'state', 'zipcode');

	public function getModuleName(){
		return __CLASS__.' : Field traps';
	}

	public function getModuleVersionInfo(){
		return 'Koko BBS Release 1';
	}

	public function autoHookPostForm(&$txt){
		foreach ($this->fields as &$f) {
			$txt .= '<input maxlength="100" type="text" name="'.$f.'" id="'.$f.'" size="28" value="" class="inputtext" style="display: none;">';
		}
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo){
		foreach ($this->fields as &$f) {
			if ($_POST[$f] != "") die;
		}
	}
}