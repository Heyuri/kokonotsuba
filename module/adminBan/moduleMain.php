<?php

namespace Kokonotsuba\Modules\adminBan;

use Kokonotsuba\ip\IPAddress;
use Kokonotsuba\module_classes\abstractModuleMain;

use function Puchiko\json\isJavascriptRequest;
use function Puchiko\json\renderJsonErrorPage;

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
		// read ban logs
		$glog = is_file($this->GLOBAL_BANS) ? array_map('rtrim', file($this->GLOBAL_BANS)) : [];
		$log = is_file($this->BANFILE) ? array_map('rtrim', file($this->BANFILE)) : [];

		// check local board bans first, then global bans
		$this->handleBan($log, $ipAddress, $this->BANFILE);
		$this->handleBan($glog, $ipAddress, $this->GLOBAL_BANS);
	}
	
	private function ipMatchesBan(string $ip, string $pattern): bool {
		// Validate IPv4 or IPv6
		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			return false;
		}

		// IPv6 handling
		if (str_contains($ip, ':') && str_contains($pattern, ':')) {
			return $this->ipv6MatchesPattern($ip, $pattern);
		}

		// IPv4 handling (simple wildcard match)
		if (str_contains($ip, '.') && str_contains($pattern, '.')) {
			// Convert wildcard pattern to regex and match
			$patternRegex = '/^' . str_replace('\*', '[0-9.]+', preg_quote($pattern, '/')) . '$/';
			return preg_match($patternRegex, $ip) === 1;
		}

		return false;
	}

	private function ipv6MatchesPattern(string $ip, string $pattern): bool {
		// Expand both IP and pattern to full IPv6 form
		$ipFull = inet_ntop(inet_pton($ip));
		$patternParts = explode(':', $pattern);
		$ipParts = explode(':', $ipFull);

		// Normalize IPv6 to 8 hextets
		if (count($ipParts) < 8) {
			$missing = 8 - count($ipParts);
			$expanded = [];
			foreach ($ipParts as $part) {
				if ($part === '') {
					for ($i = 0; $i <= $missing; $i++) $expanded[] = '0';
				} else {
					$expanded[] = $part;
				}
			}
			$ipParts = $expanded;
		}

		// Expand :: in pattern
		if (count($patternParts) < 8 && in_array('', $patternParts, true)) {
			$missing = 8 - count($patternParts) + 1;
			$expanded = [];
			$expandedOnce = false;
			foreach ($patternParts as $part) {
				if ($part === '' && !$expandedOnce) {
					for ($i = 0; $i < $missing; $i++) $expanded[] = '0';
					$expandedOnce = true;
				} else {
					$expanded[] = $part;
				}
			}
			$patternParts = $expanded;
		}

		$count = min(count($patternParts), count($ipParts));
		for ($i = 0; $i < $count; $i++) {
			if ($patternParts[$i] === '*') {
				// Trailing wildcard matches any remaining hextets
				return true;
			}
			if (strcasecmp($patternParts[$i], $ipParts[$i]) !== 0) {
				return false;
			}
		}
		// If pattern is shorter and ends with *, match
		if (end($patternParts) === '*') {
			return true;
		}
		// If all compared parts matched and lengths are equal, match
		return count($patternParts) === count($ipParts);
	}

	private function handleBan(&$log, $ip, $banFile): void {
		// Loop through each ban entry in the log
		foreach ($log as $i => $entry) {
			// Each entry is expected to be in the format: ip,starttime,expires,reason
			[$banip, $starttime, $expires, $reason] = explode(',', $entry, 4);

			// Check if the IP matches the ban pattern (supports wildcards)
			$match = $this->ipMatchesBan($ip, $banip);
			
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
