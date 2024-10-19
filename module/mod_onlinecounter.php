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
		touch($this->usercounter);
		$usr_arr = file($this->usercounter);

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
		$pageHTML = '';
		
		//make the regular user count hidden if js is disabled
		$pageHTML .= ' <style>.noscriptonly { display: none}</style> <noscript><style> .jsonly { display: none } .noscriptonly { display: contents; } </style></noscript>';
		
		$userCount = $this->getUserCount();
		$userCounterHTML = '<li class ="jsonly"><div data-timeout="'.$this->timeout.'" data-modurl="'.$this->mypage.'&usercountjson" id="usercounter"><b id="countnumber">' . $userCount . '</b> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minute'.($this->timeout > 1 ? 's' : '').' (including lurkers)</div></li>';
		$pageHTML .= $userCounterHTML;
		
		$form .= $pageHTML;
		$form .= '<li class="noscriptonly"><noscript> <iframe src="'.$this->mypage.'" style="border-right: 0px; border-bottom: 0px; font-size: small; height: 1.9em; width: 100%; vertical-align: text-bottom;" scrolling="no" frameborder="0"></iframe></noscript></li>';
	}
	
	public function ModulePage() {
		if(isset($_GET['usercountjson'])) {
			echo json_encode($this->getUserCount() ?? []);
			return;
		}
		
		
		$pageHTML = '';
		
		//add css so it appears properly inside iframe
		$pageHTML .= '<link rel="stylesheet" href="'.$this->config['STATIC_URL'].'css/base.css">';
		
		$userCount = $this->getUserCount();
		$userCounterHTML = '<div id="usercounter" value="'.$this->timeout.'"><b id="countnumber">' . $userCount . '</b> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minutes (including lurkers)</div>';
		$pageHTML .= $userCounterHTML;
		
		echo $pageHTML;
	}

}
