<?php

namespace Kokonotsuba\Modules\adminBan;

use IPAddress;
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
		// check if the regist ip is banned
		$this->checkBans($ipAddress);
	}

	private function checkBans(string $ipAddress): void {
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		$this->handleBan($log, $ipAddress, $this->BANFILE);
		$this->handleBan($glog, $ipAddress, $this->GLOBAL_BANS, true);
	}

	private function handleBan(&$log, $ip, $banFile, $isGlobalBan = false): void {
		// Loop through each ban entry in the log
		foreach ($log as $i => $entry) {
			// Each entry is expected to be in the format: ip,starttime,expires,reason
			[$banip, $starttime, $expires, $reason] = explode(',', $entry, 4);

			// For global bans, match by prefix (e.g. "127.0.0.")
			// For regular bans, match the full IP exactly
			$match = $isGlobalBan ? (strpos($ip, $banip) === 0) : ($ip === $banip);
			
			if ($match) {
				// If the ban has expired, remove it from the log and save
				// only clear it for non-js requests (i.e the user is viewing it)
				if ($_SERVER['REQUEST_TIME'] > intval($expires) && !isJavascriptRequest()) {
					unset($log[$i]);
					file_put_contents($banFile, implode(PHP_EOL, $log));
				}
			
				// then handle the rendering of the regist ban page
				$this->handleRegistBanPage($starttime, $expires, $reason, $this->BANIMG);
			}
		}
	}

	private function handleRegistBanPage(int $starttime, int $expires, string $reason, string $banImage): void {
		// render ban json page
		// this is so the javascript can alert the user that they're banned when trying to submit a post
		if(isJavascriptRequest()) {
			// get the module page url
			$moduleUrl = $this->getModulePageURL([], false, true); 

			// generate ban message for error
			$banJsonMessage = 'You are banned! [url=' . $moduleUrl . ']View ban details[/url]';
		
			// then output the json error
			renderJsonErrorPage($banJsonMessage, 403);
		}
		// This is a regular post regist request - render the normal ban page
		else {
			// show ban page
			$htmlOutput = $this->drawBanPage($starttime, $expires, $reason, $banImage);

			// Stop further execution and show the ban page
			die($htmlOutput);
		}
	}

	/**
	 * Build template values for a ban page.
	 *
	 * @param int    $starttime   Unix timestamp when the ban started.
	 * @param int    $expires     Unix timestamp when the ban ends.
	 * @param string $reason      Why the user was banned.
	 * @param string $banImage    URL or path to the ban image.
	 *
	 * @return array              Key → value pairs for the ban template.
	 */
	private function getBanTemplateValues($starttime, $expires, $reason, $banImage = '', bool $isBanned = true) {

		// True = the ban time has passed
		$isExpired = ($_SERVER['REQUEST_TIME'] > intval($expires));

		// Determine what type of moderation action it was:
		// If start == expire, it's a warning, otherwise a ban.
		$banType = ($starttime == $expires) ? 'warned' : 'banned';

		// If expired, show simple message.
		// Otherwise, show detailed ban-time info.
		$detailText = $isExpired
			? 'Now that you have seen this message, you can post again.'
			: "<p>Your ban was filed on " .
				date('Y/m/d \a\t H:i:s', $starttime) .
				" and expires on " .
				date('Y/m/d \a\t H:i:s', $expires) .
				".</p>";

		// Build template variables; the template engine expects literal placeholders.
		return [
			'{$RETURN_URL}' => $this->config['STATIC_INDEX_FILE'] ?? './',
			'{$IS_BANNED}'  => $isBanned,        // tells template “use banned layout”
			'{$BAN_TYPE}'   => $banType,
			'{$REASON}'     => $reason,
			'{$BAN_IMAGE}'  => $banImage,
			'{$BAN_DETAIL}' => $detailText
		];
	}


	/**
	 * Render the ban page using the template values.
	 */
	private function drawBanPage($starttime, $expires, $reason, $banImage = ''): string {
		$templateValues = $this->getBanTemplateValues($starttime, $expires, $reason, $banImage);

		return $this->moduleContext
			->adminPageRenderer
			->ParsePage('BAN_PAGE', $templateValues);
	}

	private function drawNotBannedPage(): void {
		// get the not-banned image url from config
		$notBannedImage = $this->getConfig('STATIC_URL') . 'image/notbanned.png';
		
		// get the template values
		$templateValues = $this->getBanTemplateValues(0, 0, "You can post!", $notBannedImage, false);

		// render template
		$notBannedPageHtml = $this->moduleContext->adminPageRenderer->ParsePage('BAN_PAGE', $templateValues);

		// echo output
		echo $notBannedPageHtml;
	}

	public function ModulePage(): void {
		// get the user's IP
		$ipAddress = new IPAddress();

		// check if they're banned
		// execution won't continue past here if a ban is caught since it terminates output with exit
		$this->checkBans($ipAddress);

		// if execution reaches here then we can render a cute "You are not banned" page!
		// render not-banned page
		$this->drawNotBannedPage();
	}
}
