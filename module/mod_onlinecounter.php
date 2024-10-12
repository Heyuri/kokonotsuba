<?php
class mod_onlinecounter extends ModuleHelper {
	private $mypage, $usercounter, $timeout;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->usercounter = $this->config['ModuleSettings']['USER_COUNT_DAT_FILE'];
		$this->timeout = $this->config['ModuleSettings']['USER_COUNT_TIMEOUT'];
		$this->mypage = $this->getModulePageURL();
	}

	public function getModuleName() {
		return __CLASS__.': Online user count module';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba 2024';
	}

	private function getUserCount() {
		$usr_arr = file($this->usercounter);
		touch($this->usercounter);

		$fp = fopen($this->usercounter, "w");
		$currentTimeInMinutes = floor(time() / 60);
		$addr = $_SERVER['REMOTE_ADDR'];

		foreach ($usr_arr as $line) {
			$line = trim($line);
			if(empty($line)) continue;
			
			list($ip_addr, $stamp) = explode("|", $line);
			// Ensure $stamp is a valid numeric value
			if (is_numeric($stamp) && ($currentTimeInMinutes - $stamp) < $this->timeout && $ip_addr != $addr) fputs($fp, $ip_addr . '|' . $stamp . "\n");
		}
		fputs($fp, $addr . '|' . $currentTimeInMinutes . "\n");
		fclose($fp);

		return count($usr_arr);
	}
	
	public function autoHookPostInfo(&$form) {
		$userCount = $this->getUserCount();
		$userCounterHTML = '<li><div id="usercounter"><b>' . $userCount . '</b> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minutes (including lurkers)</div></li>';
		$form .= $userCounterHTML;
	}

}
