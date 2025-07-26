<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\ModuleClasses\abstractModuleMain;

class moduleMain extends abstractModuleMain {
	private string $BANFILE = '';
	private string $GLOBAL_BANS = '';
	private string $BANIMG = '';

	public function getName(): string {
		return 'K! Admin Ban';
	}

	public function getVersion(): string {
		return 'Kokonotsuba 2025';
	}

	public function initialize(): void {
		$this->BANFILE = $this->moduleContext->board->getBoardStoragePath() . 'bans.log.txt';
		$this->BANIMG = $this->getConfig('STATIC_URL') . "image/banned.jpg";
		$this->GLOBAL_BANS = getBackendGlobalDir() . $this->getConfig('GLOBAL_BANS');

		touch($this->BANFILE);
		touch($this->GLOBAL_BANS);

		$this->moduleContext->moduleEngine->addListener('RegistBegin', function (array &$registInfo) {
			$this->onRegistBegin($registInfo['ip']);  // Call the method to modify the form
		});
	}

	public function onRegistBegin(string $ipAddress): void {
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		$this->handleBan($log, $ipAddress, $this->BANFILE);
		$this->handleBan($glog, $ipAddress, $this->GLOBAL_BANS, true);
	}

	private function handleBan(&$log, $ip, $banFile, $isGlobalBan = false): void {
		$htmlOutput = '';
		
		// Loop through each ban entry in the log
		foreach ($log as $i => $entry) {
			// Each entry is expected to be in the format: ip,starttime,expires,reason
			[$banip, $starttime, $expires, $reason] = explode(',', $entry, 4);

			// For global bans, match by prefix (e.g. "127.0.0.")
			// For regular bans, match the full IP exactly
			$match = $isGlobalBan ? (strpos($ip, $banip) === 0) : ($ip === $banip);
			
			if ($match) {
				// If IP matches, show ban page
				$htmlOutput .= $this->drawBanPage($starttime, $expires, $reason, $this->BANIMG);
				
				// If the ban has expired, remove it from the log and save
				if ($_SERVER['REQUEST_TIME'] > intval($expires)) {
					unset($log[$i]);
					file_put_contents($banFile, implode(PHP_EOL, $log));
				}
				
				// Stop further execution and show the ban page
				die($htmlOutput);
			}
		}
	}

	private function drawBanPage($starttime, $expires, $reason, $banImage = '') {
		$isExpired = ($_SERVER['REQUEST_TIME'] > intval($expires));

		$templateValues = [
				'{$RETURN_URL}'	=> $this->config['STATIC_INDEX_FILE'] ?? './',
				'{$BAN_TYPE}'		=> ($starttime == $expires) ? 'warned' : 'banned',
				'{$REASON}'			=> $reason,
				'{$BAN_IMAGE}'		=> $banImage,
				'{$BAN_DETAIL}'	=> $isExpired
					? 'Now that you have seen this message, you can post again.'
					: "<p>Your ban was filed on " . date('Y/m/d \a\t H:i:s', $starttime) .
						" and expires on " . date('Y/m/d \a\t H:i:s', $expires) . ".</p>"
		];

		return $this->moduleContext->adminPageRenderer->ParsePage('BAN_PAGE', $templateValues);
	}

}
