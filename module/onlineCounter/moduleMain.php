<?php

namespace Kokonotsuba\Modules\onlineCounter;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private $myPage, $usercounter, $timeout, $staticUrl;

	public function getName(): string {
		return 'Online user count module';
	}

	public function getVersion(): string  {
		return 'Kokonotsuba 2024';
	}

	public function initialize(): void {
		$this->timeout = $this->getConfig('ModuleSettings.USER_COUNT_TIMEOUT');
		
		$this->staticUrl = $this->getConfig('STATIC_URL');
		
		$this->usercounter = $this->moduleContext->board->getBoardStoragePath().$this->getConfig('ModuleSettings.USER_COUNT_DAT_FILE');
		
		$this->myPage = $this->getModulePageURL();

		$this->moduleContext->moduleEngine->addListener('PostInfo', function(string &$hookPostInfoHtml) {
			$this->onRenderPostInfo($hookPostInfoHtml);
		});
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
	
	public function onRenderPostInfo(string &$hookPostInfoHtml): void {
		$userCount = $this->getUserCount();
		$userCounterHTML = '
			<li id="counterListItemJS" class="hidden">
				<div data-timeout="'.$this->timeout.'" data-modurl="'.$this->myPage.'&usercountjson" id="usercounter">
					<span id="countnumber">' . $userCount . '</span> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minute'.($this->timeout > 1 ? 's' : '').' (including lurkers)
				</div>
			</li>';

		$noScriptHtml = '
			<li id="counterListItemNoJS" class="">
				<noscript>
					<iframe id="counterIframe" title="User counter" src="'.$this->myPage.'"></iframe>
				</noscript>
			</li>';

		$hookPostInfoHtml .= $noScriptHtml . $userCounterHTML;
	}
	
	public function ModulePage() {
		if(isset($_GET['usercountjson'])) {
			echo json_encode($this->getUserCount() ?? []);
			return;
		}

		$pageHTML = '<!DOCTYPE html><html>';
		
		//add css so it appears properly inside iframe
		$pageHTML .= '
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<link rel="stylesheet" href="' . $this->staticUrl . 'css/kokoimg/base.css">
			</head>
			<body id="counterIframeBody">';
		$userCount = $this->getUserCount();
		$userCounterHTML = '<div id="usercounter" value="'.$this->timeout.'"><span id="countnumber">' . $userCount . '</span> unique user' . ($userCount > 1 ? 's' : '') . ' in the last '.$this->timeout.' minutes (including lurkers)</div>';
		$pageHTML .= $userCounterHTML;
		$pageHTML .= '</body></html>';
		
		echo $pageHTML;
	}

}
